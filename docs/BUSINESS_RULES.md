# klinika-web Business Rules

## Status

The master PRD is unavailable because `projectplan.md` is empty. The rules below are safe proposed defaults, not approved product requirements. Every item marked “decision required” must be confirmed before its feature is implemented.

## Booking invariants

- An appointment belongs to exactly one clinic, patient, practitioner, and service unless the approved scope changes that model.
- Start and end are required instants, and end must be after start.
- Appointment intervals are half-open (`[start, end)`), allowing adjacent appointments.
- A practitioner may not have overlapping appointments in a blocking status.
- The practitioner must be active, belong to the clinic, and be eligible to provide the service.
- The appointment must fit applicable availability after schedule exceptions and approved buffers are applied.
- Availability displayed to a user is informational until the booking transaction commits.
- Create and reschedule operations must be idempotent for retried submissions and safe under concurrent requests.
- Status changes and cancellations retain historical attribution; operational records are not silently deleted.
- Notifications occur only after the transaction commits and never determine booking success.

Decisions required: slot granularity, minimum notice, maximum advance window, service duration source, buffers, overbooking, walk-ins, approval flow, reschedule/cancellation cutoff, no-show handling, recurring appointments, and whether patient conflicts also block bookings.

## Proposed appointment state model

Candidate statuses are `Pending`, `Confirmed`, `Completed`, `Cancelled`, and `NoShow`. Proposed transitions are:

| From | Allowed destinations |
| --- | --- |
| Pending | Confirmed, Cancelled |
| Confirmed | Completed, Cancelled, NoShow |
| Completed | None through normal UI |
| Cancelled | None through normal UI |
| NoShow | None through normal UI |

Whether `Pending` is needed, which statuses block time, and whether administrators may correct terminal states are decisions required from the PRD owner.

## Proposed permissions

| Capability | Administrator | Receptionist | Practitioner | Patient portal |
| --- | --- | --- | --- | --- |
| Manage users and roles | Yes | No | No | No |
| Manage clinic settings/services | Yes | No | No | No |
| Manage practitioner schedules | Yes | Proposed limited | Proposed own only | No |
| View clinic calendar | Yes | Yes | Own only | Own appointments only |
| Create/reschedule appointments | Yes | Yes | Proposed own schedule | Own only, if enabled |
| Cancel appointments | Yes | Yes | Proposed own schedule | Own only within policy |
| Manage patients | Yes | Yes | Read permitted patients | Own profile only |
| View audit history | Yes, scoped | No | No | No |

These are policy intentions, not merely navigation visibility. Exact practitioner access to patient data, receptionist administrative powers, custom roles, emergency access, and cross-clinic access require approval.

## Identity and patient-data rules

- User accounts and patient records are distinct; a patient need not be a login user unless self-service is approved.
- Define the authoritative patient identifier and duplicate matching process. Names alone are not unique.
- Collect only data needed by approved workflows.
- Sensitive fields, exports, and audit entries follow least privilege and an approved retention policy.
- Account disabling must not erase historical attribution.
- Merges, corrections, deletions, anonymization, and legal holds require explicit workflows before production.

## Scheduling timezone rules

- Store appointment instants in UTC.
- Store each clinic's IANA timezone and interpret recurring availability in clinic-local time.
- Define daylight-saving behavior for nonexistent or repeated local times.
- A timezone change must not silently move existing appointment instants.

The initial clinic timezone and whether clinics can span timezones are decisions required.

## Audit rules

At minimum, record appointment creation, rescheduling, status/cancellation changes, access-control changes, and sensitive exports. Each entry needs actor (or system), action, target, clinic scope, timestamp, and appropriate before/after context. Avoid copying full medical or patient payloads into logs. Retention and who may view audit history must be approved.


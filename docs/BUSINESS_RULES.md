# klinika-web Business Rules

## Scope and identities

- klinika-web serves one clinic location with multiple doctors.
- Supported roles are patient, receptionist, and admin.
- Every patient has exactly one authenticated user account in the MVP.
- Doctors do not have accounts in the MVP.
- Patients self-register; staff do not create walk-in appointments.
- Only active doctors accept new requests.
- Medical records, diagnosis, payments, and in-place rescheduling are out of scope.

## Booking rules

- A patient must be authenticated with an active patient account; email verification is not required.
- The patient is derived from the authenticated account and cannot be selected from client input.
- The patient selects an active doctor, date, and available start time.
- Starts align to a 15-minute grid; duration comes from the doctor and is 15, 30, 45, or 60 minutes.
- The complete interval must fit one effective working interval and must not overlap unavailability.
- Bookings cannot be in the past, must have at least 4 hours' lead time, must leave at least a 2-hour receptionist review window, and cannot be more than 30 days ahead.
- Every new request starts `pending` and requires receptionist approval.
- Pending and confirmed requests block every doctor and patient 15-minute slot they occupy.
- A doctor and a patient may not have overlapping active appointments.
- Availability display is advisory; only the atomic booking transaction confirms occupancy.
- A doctor's later default-duration change never alters existing appointments.

## Pending expiry

`expires_at` is the earlier of:

- 24 hours after submission; or
- 2 hours before appointment start.

Expiry locks and rechecks the appointment, changes `pending` to `expired`, releases reservations, appends an event, and commits atomically. Approval must independently reject an expired request even if scheduled cleanup has not run.

## Approval and rejection

- Only a receptionist-authorized workflow may approve or reject.
- Approval is allowed only for an unexpired `pending` request and retains its reservations.
- Rejection is allowed only from `pending`, requires a short reason, and releases reservations atomically.
- The system never accepts an arbitrary status submitted by the browser.

## Cancellation and attendance

- A patient may cancel their own `pending` or `confirmed` appointment until 2 hours before start.
- Authorized staff may perform a late-cancellation exception with an audit reason.
- Cancellation releases reservations but preserves the appointment and history.
- A receptionist may check in a `confirmed` appointment.
- Only a `checked_in` appointment may become `completed`.
- A receptionist may mark `confirmed` as `no_show` only after the 15-minute grace period.
- No-show is never automatic.
- Rescheduling is cancel-and-rebook; cancellation does not guarantee another slot.

## Status transitions

| From | Allowed destination | Reservation effect |
| --- | --- | --- |
| `pending` | `confirmed` | Retain |
| `pending` | `rejected` | Release |
| `pending` | `expired` | Release |
| `pending` | `cancelled` | Release |
| `confirmed` | `checked_in` | Retain for history/workflow |
| `confirmed` | `cancelled` | Release |
| `confirmed` | `no_show` | Release/no longer active |
| `checked_in` | `completed` | Release/no longer active |

Terminal states do not transition through normal workflows.

## Permissions

| Capability | Patient | Receptionist | Admin |
| --- | --- | --- | --- |
| Public registration | Yes | No | No |
| View doctors and availability | Yes | Yes | Yes |
| Request appointment | Own only | No walk-ins | No walk-ins |
| View appointments | Own only | Clinic operations | Summaries/audit as authorized |
| Approve/reject | No | Yes | Only if separately authorized by policy |
| Check in/complete/no-show | No | Yes | Only if separately authorized by policy |
| Cancel | Own, within cutoff | Authorized exceptions | Authorized exceptions |
| Manage doctors/specialties/schedules | No | No | Yes |
| Manage receptionist accounts/settings | No | No | Yes |
| View audit history | Own appointment history only | Operational history as required | Yes |

Authorization is enforced in policies and actions, not merely by hiding controls.

## Audit and privacy

Append an event for appointment creation and every transition, including actor, old/new status, time, and required reason. Audit schedule and setting changes durably. Store restricted metadata only and never copy passwords, secrets, diagnoses, or unnecessary contact details into audit records or logs.

Use fictional data until privacy, legal, security, retention, deletion/anonymization, breach-response, and operational readiness reviews approve real patient data.

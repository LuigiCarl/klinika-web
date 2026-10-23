# klinika-web Product Requirements

## Authority and status

[`projectplan.md`](../projectplan.md) is the complete master PRD and the primary source of truth. This file is a concise implementation reference; it does not replace or override the master.

The repository previously proceeded from provisional multi-clinic requirements while `projectplan.md` was empty. Those assumptions are superseded. Existing code is preserved for review, but conflicting behavior is not an approved product requirement.

## Product scope

klinika-web is a standalone appointment platform for **one clinic location with multiple doctors**.

MVP users are patients, receptionists, and administrators. Patients register, log in without email verification, browse doctors and availability, submit appointment requests, monitor status, and cancel eligible appointments. Receptionists approve or reject requests and manage attendance. Administrators manage doctors, specialties, schedules, unavailability, receptionist accounts, booking policies, and audit information.

Explicitly out of scope:

- multi-clinic SaaS or tenant membership;
- doctor login accounts;
- walk-in registration;
- medical records, diagnoses, prescriptions, or clinical notes;
- payments;
- in-place appointment rescheduling;
- React, Next.js, Inertia, a separate SPA, or a separate REST API.

## Roles

### Patient

May self-register, use the account without email verification, manage a basic profile, browse doctors, request an available appointment, view only their own appointments, and cancel within policy. A patient may never choose an arbitrary patient record or access another patient's information.

### Receptionist

May review pending requests, approve or reject them, operate the appointment calendar, search and inspect appointments, check patients in, complete checked-in appointments, record no-shows, and perform authorized late cancellations with a reason. Receptionists do not create walk-in appointments or administer doctors, clinic policy, or administrator accounts.

### Administrator

May manage doctors, specialties, schedules, unavailability, duration settings, clinic policies, receptionist accounts, summaries, configuration, and audit history. Administrators are provisioned securely and never through public registration.

## Appointment lifecycle

Statuses are `pending`, `confirmed`, `rejected`, `expired`, `cancelled`, `checked_in`, `completed`, and `no_show`.

Allowed transitions are:

```text
pending    -> confirmed | rejected | expired | cancelled
confirmed  -> checked_in | cancelled | no_show
checked_in -> completed
```

All other transitions are prohibited. A centralized transition service must authorize, validate, lock, mutate, and audit each transition.

## Default policy

- Clinic timezone: `Asia/Manila`; persist appointment instants in UTC.
- Appointment duration per doctor: 15, 30, 45, or 60 minutes.
- Scheduling grid: 15 minutes.
- Maximum advance booking: 30 days.
- Minimum lead time: 4 hours.
- Minimum receptionist review window: 2 hours.
- Pending expiry: the earlier of 24 hours after submission or 2 hours before start.
- Patient cancellation cutoff: 2 hours before start.
- No-show grace period: 15 minutes.
- Pending and confirmed appointments reserve both doctor and patient slots.

Configuration may alter the policy fields identified in the master PRD, but changes must not rewrite historical appointment duration or silently invalidate existing bookings.

## Non-functional requirements

- Server-side authentication, validation, authorization, ownership enforcement, rate limiting, CSRF protection, secure sessions, safe errors, secrets management, and minimal personal-data collection.
- Atomic PostgreSQL booking and mutation transactions with deterministic locks and database-enforced unique slot reservations.
- Append-only appointment events and durable audit records for schedule and configuration changes.
- Responsive and accessible Blade/Livewire UI with clear states, errors, loading feedback, keyboard focus, and reduced-motion support.
- Automated authentication, scheduling, booking, workflow, concurrency, and deployment-readiness tests as their phases are implemented.
- Render deployment only in Phase 9 and only after explicit approval.

## Deferred implementation decisions

1. **Framework major:** the master specifies Laravel 12; the installed and previously approved foundation is Laravel 13. Product-owner confirmation is required to retain Laravel 13 as a controlled deviation.
2. **Local database:** MariaDB is temporarily accepted for local work. PostgreSQL remains authoritative, and Phase 4 cannot be accepted without PostgreSQL concurrency verification.
3. **Email verification and delivery:** email verification is removed by product-owner decision. Notification delivery remains deferred and does not gate login or booking.
4. **Privacy readiness:** jurisdiction, retention, deletion/anonymization, breach response, and real-patient-data approval must be resolved before a production pilot.

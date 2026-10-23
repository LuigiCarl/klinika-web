# klinika-web Database Design

## Authority

PostgreSQL is the production source of truth. MariaDB is a temporary local convenience only and cannot prove the required concurrency guarantees.

Implementation status: the corrected Phase 2 schema, models, factories, seed data, relationships, foreign keys, uniqueness rules, and portable check constraints are implemented and pass locally on MariaDB. PostgreSQL verification is still required.

## Target relationships

```text
users 1---0..1 patients
specialties 1---* doctors
doctors 1---* doctor_schedules
doctors 1---* doctor_unavailabilities
patients 1---* appointments *---1 doctors
appointments 1---* doctor_slot_reservations
appointments 1---* patient_slot_reservations
appointments 1---* appointment_events
users 0..1---* appointment_events (actor)
users 0..1---* appointments (approver/canceller)
clinic_settings (single-clinic policy row)
```

There is no `clinics` tenant table, clinic membership pivot, practitioner account, service catalog, or practitioner-service pivot in the approved MVP.

## Target tables

| Table | Required purpose and constraints |
| --- | --- |
| `users` | Authentication identity; unique email; role restricted to `patient`, `receptionist`, or `admin`; `is_active`; password fields. The starter's nullable `email_verified_at` column is retained but unused. |
| `patients` | One-to-one patient profile through unique `user_id`; optional phone; each MVP patient has a user account. |
| `specialties` | Unique name and active flag. |
| `doctors` | Specialty, name, active flag, and duration restricted to 15/30/45/60 minutes; no login identity. |
| `doctor_schedules` | Doctor, weekday, local start/end time, optional effective dates; supports multiple non-overlapping daily intervals. |
| `doctor_unavailabilities` | Doctor, UTC start/end instants, and short operational reason. |
| `appointments` | Patient, doctor, unique reference, UTC interval, immutable booked duration, controlled status, expiry/approval/cancellation attribution, idempotency key, and request fingerprint. |
| `doctor_slot_reservations` | One row for each occupied 15-minute doctor slot; unique `(doctor_id, slot_starts_at)`. |
| `patient_slot_reservations` | One row for each occupied 15-minute patient slot; unique `(patient_id, slot_starts_at)`. |
| `appointment_events` | Append-only transition and action trail with optional actor/reason and restricted metadata. |
| `clinic_settings` | Single-clinic timezone and configurable booking, review, expiry, cancellation, and no-show policies. |

Framework session, password-reset, cache, jobs, failed-jobs, and authentication-support tables may remain where required.

## Integrity rules

- Use foreign keys and restrictive deletion for historical or referenced operational records.
- Require `ends_at > starts_at`, valid duration values, a valid status, and 15-minute-aligned booking starts.
- Enforce unique `(patient_id, idempotency_key)` and unique appointment reference codes.
- Ensure reservation rows reference the same doctor or patient as their appointment.
- Index appointment queries by doctor/start, patient/start, status/start, and expiry as actual query plans require.
- Pending and confirmed appointments own reservations. Rejection, expiry, and cancellation release them atomically; attendance transitions do not create a second booking interval.
- Do not cascade-delete appointment or event history.
- Keep appointment events append-only and separately audit doctor schedule and clinic-setting changes.

## Slot representation

A 45-minute appointment beginning at 09:00 creates doctor and patient reservations at 09:00, 09:15, and 09:30. An appointment ending at 09:45 does not conflict with one starting at 09:45.

The reservation unique indexes, not an availability query, are the final protection against simultaneous bookings. Booking must catch only the relevant PostgreSQL unique-violation case and return a conflict without exposing database details.

## Idempotency

The application creates a unique submission key for each booking attempt and stores a deterministic fingerprint of the authoritative request payload. Reusing the same key and fingerprint returns the original logical result; reusing the key with a different fingerprint is rejected. Idempotency checks occur under the booking transaction's lock protocol.

## Migration strategy from provisional code

The repository currently contains incompatible multi-clinic tables and migrations. Do not silently edit already-applied migrations or destructively drop local data during documentation reconciliation. Corrected Phase 2 must first inventory whether any non-demo data needs preservation, then create an explicit replacement/migration plan. Because the project is pre-release, a clean development-database rebuild may be the simplest option, but it requires product-owner approval before destructive execution.

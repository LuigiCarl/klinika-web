# klinika-web Database Design

## Phase 3 tables

- `patients` belongs to a clinic and stores normalized contact fields for duplicate warnings.
- `appointments` belongs to one clinic, patient, practitioner, and service. Composite foreign keys enforce clinic consistency.
- `appointment_audits` records actor, action, timestamp, and limited before/after operational state without copying patient contact data.
- Pending and confirmed intervals are serialized with practitioner/patient row locks. PostgreSQL additionally uses transaction advisory locks and partial GiST exclusion constraints for practitioner and patient overlaps.

## Status

This is a provisional PostgreSQL design. Table names, fields, cardinality, and retention must be reconciled with the missing master PRD before migrations are created.

## Design principles

- PostgreSQL is authoritative in development, testing, and production to avoid SQLite/PostgreSQL behavior drift.
- Use conventional plural table names and bigint primary keys unless an approved external/public identifier requirement calls for UUID/ULID identifiers.
- Store timestamps in UTC and render them in the clinic's approved IANA timezone.
- Use foreign keys, not-null constraints, check constraints, and unique indexes for invariants the database can enforce.
- Prefer `restrict` deletion for referenced clinical/operational records. Use archival or status fields where history must remain.
- Never use floating point for money; use integer minor units plus currency if billing is approved.
- Add indexes for actual query shapes and verify them with representative data and query plans.

## Proposed relationships

```text
clinics 1---* clinic_user *---1 users
clinics 1---* patients
clinics 1---* services
clinics 1---* practitioners
users   0..1---1 practitioners
practitioners *---* services (practitioner_service)
practitioners 1---* availability_rules
practitioners 1---* schedule_exceptions
patients      1---* appointments
practitioners 1---* appointments
services      1---* appointments
clinics       1---* appointments
appointments  1---* appointment_status_histories
users         1---* audit_logs (actor, nullable for system actions)
```

If klinika-web is confirmed single-clinic, `clinics` and membership scoping may be removed rather than carrying speculative multi-tenancy.

## Proposed tables

| Table | Purpose and notable constraints |
| --- | --- |
| `users` | Authentication identity; unique normalized email; status and verification fields. |
| `clinics` | Clinic identity, timezone, contact/configuration data. Include only if multi-clinic or future clinic separation is approved. |
| `clinic_user` | Clinic membership and role; unique `(clinic_id, user_id)`; constrained role value. Implemented in Phase 2. |
| `patients` | Clinic-scoped demographics and contact data; identifiers and uniqueness depend on approved duplicate rules. |
| `practitioners` | Clinic-scoped practitioner profile, optionally linked to a user account. |
| `services` | Clinic-scoped bookable service, duration, optional buffer, active flag. |
| `practitioner_service` | Which practitioners provide which services; unique pair; optional duration override only if required. |
| `availability_rules` | Recurring weekly availability with day, local start/end time, effective dates. |
| `schedule_exceptions` | Dated closures or additional availability with interval and reason. |
| `appointments` | Clinic, patient, practitioner, service, `[starts_at, ends_at)`, status, source, cancellation metadata, creator/updater, and optimistic metadata if required. |
| `appointment_status_histories` | Append-only status transitions with actor, timestamp, and reason. |
| `audit_logs` | Append-only record of sensitive changes; avoid duplicating unnecessary patient data. |

Rooms, equipment, payments, reminders, waitlists, clinical notes, and attachments are intentionally excluded until the PRD confirms them.

## Appointment integrity

Represent appointment intervals as half-open ranges: a booking ending at 10:00 does not conflict with one starting at 10:00. Require `ends_at > starts_at`. Persist the actual start and end instants so later service-duration changes do not rewrite history.

For PostgreSQL, the preferred hard guarantee is an exclusion constraint on active appointments for the same practitioner:

```sql
EXCLUDE USING gist (
    practitioner_id WITH =,
    tstzrange(starts_at, ends_at, '[)') WITH &&
)
WHERE (status IN ('pending', 'confirmed'))
```

This requires the `btree_gist` extension for equality on bigint identifiers. The exact blocking status set must be approved. If rooms or other exclusive resources are approved, add equivalent constraints for those resources rather than relying on application checks.

Because PostgreSQL exclusion constraints cannot use a mutable application table as their predicate, status values used by the partial constraint must be stable database values. Status transitions and constraint changes require careful migrations.

## Concurrency strategy

Booking and rescheduling use a short database transaction. The operation should take a deterministic practitioner/day advisory transaction lock (or lock a dedicated schedule row), then re-check availability and persist. The exclusion constraint is the final race-proof safeguard. A constraint violation is translated into a friendly “slot no longer available” result.

The application-level lock improves predictable behavior and coordinates related checks; it does not replace the exclusion constraint. Do not use cache locks as the only guard because a distributed cache outage, lease expiry, or configuration error must not permit double-booking. Keep network calls and notifications outside the transaction and dispatch them only after commit.

Cancellation releases the protected interval only when its status leaves the approved blocking set. Rescheduling updates the same appointment in a transaction so the old interval is not released before the new interval is secured.

## Index plan to validate

- appointments by `(clinic_id, starts_at, id)` for calendar ranges;
- appointments by `(practitioner_id, starts_at, id)` and `(patient_id, starts_at, id)`;
- active practitioner/service and membership lookups;
- schedule rules by practitioner and effective date;
- status history by `(appointment_id, created_at, id)`;
- audit logs by clinic, actor, auditable reference, and time according to approved screens and retention.

Avoid redundant indexes; validate the final set against migrations and representative `EXPLAIN` plans.

## Development database status

Local development temporarily uses MariaDB 10.4 through Laravel's `mariadb` connection. PostgreSQL remains the CI and production target. Phase 2 uses portable foreign keys, unique indexes, and check constraints; PostgreSQL-backed CI must verify them before production release.


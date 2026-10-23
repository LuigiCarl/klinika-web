# klinika-web Architecture

## Target architecture

klinika-web is a modular Laravel monolith. Blade renders pages, Livewire handles server-driven interaction, application services own scheduling and workflow logic, Eloquent maps the domain, and PostgreSQL is the integrity boundary.

```text
Browser -> HTTPS -> Nginx -> PHP-FPM -> Laravel/Livewire -> PostgreSQL
                                      |-> database queue worker
                                      |-> scheduler
                                      |-> structured logs
                                      `-> queued notifications after commit
```

Keep the design direct and conventional. Do not add repository interfaces, a separate API, event sourcing, microservices, or speculative abstractions.

## Application boundaries

- `app/Livewire/Patient`: patient dashboard, doctor browsing, booking, and owned appointments.
- `app/Livewire/Receptionist`: request review, calendar, attendance, and history.
- `app/Livewire/Admin`: doctors, specialties, schedules, staff, settings, and audit history.
- `app/Models`: persistence models and relationships, without orchestration-heavy business logic.
- `app/Policies`: resource and role authorization.
- `app/Services/AvailabilityService`: calculates candidate slots from schedules, unavailability, policy, and current reservations.
- `app/Services/BookingService`: performs atomic idempotent booking and reservation creation.
- `app/Services/AppointmentTransitionService`: controls allowed status changes and reservation release.
- `app/Services/DoctorScheduleService`: validates schedule changes and previews affected appointments.
- `app/Notifications`, `app/Jobs`, and scheduled commands: post-commit and background work only.

Exact folders should be introduced only when their phase is approved and a concrete class needs them.

## Security boundary

Every action must authenticate, validate server-side, authorize through middleware or policies, and re-read authoritative records inside the transaction when correctness depends on mutable state. Navigation visibility is not authorization.

Patient ownership is derived from the authenticated user's unique patient profile. Client-supplied roles, statuses, patient IDs, durations, and calculated end times are never trusted. Admin accounts are provisioned outside public registration; receptionist accounts are created and deactivated by administrators.

Audit metadata must be deliberately limited. Do not place passwords, secrets, diagnoses, or unnecessary patient contact data in logs, events, exceptions, or notification payloads.

## Time and scheduling

Store appointment timestamps in UTC and display them in `Asia/Manila`. Weekly schedules use clinic-local weekday/time values and may have multiple non-overlapping intervals per day. Unavailability uses UTC instants. Use half-open intervals (`[start, end)`) so adjacent appointments are valid.

Availability results are advisory until a booking transaction commits. The database's unique slot reservations are the final collision protection.

## Concurrency protocol

For booking and any mutation affecting occupied time:

1. Begin a PostgreSQL transaction.
2. Resolve immutable resource identifiers.
3. Lock doctor, then patient, then appointment when applicable.
4. Re-check idempotency and expire relevant stale pending requests using the same protocol.
5. Re-read doctor duration, schedules, unavailability, policy, and appointment state.
6. Validate the requested interval and state transition.
7. Create or update the appointment and insert/delete its 15-minute doctor and patient reservations.
8. Append the appointment event.
9. Commit, then dispatch notifications.

Unique `(doctor_id, slot_starts_at)` and `(patient_id, slot_starts_at)` constraints provide the hard conflict guarantee. Translate PostgreSQL unique violations into a safe “slot no longer available” response. Apply bounded retry only to recognized deadlock or serialization failures and only where the full transaction is safe to retry.

## Runtime and deployment

Production targets Docker on Render with Nginx and PHP-FPM, managed PostgreSQL in the same region, a database queue worker, a scheduler mechanism, HTTPS, secure cookies, health checks, environment-managed secrets, and tested backups. The web filesystem is ephemeral; no durable data may depend on local disk.

Deployment work belongs to Phase 9. Local development currently uses MariaDB by product-owner direction, but PostgreSQL must validate database-specific behavior before the booking engine can pass its phase gate.

## Reconciliation boundary

The current repository contains a provisional multi-clinic model (`Clinic`, `Practitioner`, `Service`, clinic memberships, staff-created bookings, and rescheduling). These artifacts conflict with the restored master PRD. They are preserved until a corrected implementation phase is approved; they must not shape the target architecture merely because they already exist.

Reusable foundation includes authentication scaffolding, Livewire/Blade/Tailwind setup, testing and analysis tooling, UTC handling, health checks, queue infrastructure, request correlation, and operational documentation. Reuse remains subject to role and framework-version alignment.

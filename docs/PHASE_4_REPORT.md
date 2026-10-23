# Phase 4 Report — Availability and Booking Engine

## Phase completed

Phase 4 is implemented and verified locally on MariaDB. The required PostgreSQL concurrency test exists but was not executed locally. Phase 5 has not started.

## Implemented

- `AvailabilityService` generates 15-minute-grid starts using doctor duration, effective weekly schedules, breaks, unavailability, clinic policy, and existing doctor/patient reservations.
- `BookingService` creates pending requests in short database transactions using the required doctor → patient lock order.
- Booking policy enforcement includes active doctor, verified/active patient, working hours, 15-minute alignment, minimum lead/review time, 30-day window, unavailability, and doctor/patient occupancy.
- Variable 15/30/45/60-minute appointments reserve every occupied 15-minute doctor and patient slot.
- Pending expiry is the earlier of the configured approval lifetime or minimum review deadline.
- Relevant stale pending requests are locked, expired, released, and audited inside the booking transaction.
- Identical idempotent retries return the original appointment; mismatched payload reuse is rejected.
- Appointment requests create a unique reference, immutable duration/interval, reservations, and append-only `requested` event atomically.
- Database unique violations on reservation tables are translated to a safe booking-conflict exception.
- Recognized transaction deadlocks receive Laravel's bounded transaction retry handling.
- No notifications or network operations occur inside booking transactions.

## Files changed

- `app/Exceptions/BookingConflictException.php`
- `app/Services/AvailabilityService.php`
- `app/Services/BookingService.php`
- `tests/Feature/AvailabilityServiceTest.php`
- `tests/Feature/BookingServiceTest.php`
- `tests/Feature/BookingConcurrencyTest.php`
- Phase status documentation.

## Commands executed

```text
php artisan make:class Services/AvailabilityService --no-interaction
php artisan make:class Services/BookingService --no-interaction
php artisan make:exception BookingConflictException --no-interaction
php artisan make:test --pest ... --no-interaction
php artisan migrate:fresh --seed --no-interaction
vendor/bin/pint --dirty --format agent
php artisan test --compact
vendor/bin/phpstan analyse --no-progress
composer audit --no-interaction
npm.cmd audit --audit-level=high
git diff --check
php artisan migrate:status --no-interaction
```

## Tests

- Full MariaDB suite: **86 total, 85 passed, 208 assertions, 1 expected PostgreSQL-only skip**.
- Focused Phase 4 suite before the final rollback case: **13 total, 12 passed, 26 assertions, 1 expected PostgreSQL-only skip**.
- Transaction rollback test: passed; a database-rejected patient slot leaves no new appointment or doctor reservation.
- PHPStan: **0 errors**.
- Pint: passed.
- All local MariaDB migrations: applied.
- `git diff --check`: passed; only the existing `projectplan.md` line-ending notice was reported.

The PostgreSQL concurrency test was not executed because PostgreSQL is not available locally. No PostgreSQL pass is claimed. Composer and npm security audits could not reach their registries because outbound network/cache access was unavailable; no current audit pass is claimed.

## Security and architecture notes

- Availability results remain advisory; database unique constraints are the final occupancy guarantee.
- Patient identity is supplied as an authenticated-domain patient and revalidated as active, verified, and assigned the patient role.
- The request fingerprint covers the patient-requested doctor and UTC start, while duration is reread from the locked doctor and persisted on the appointment.
- Idempotency, stale expiry, validation, appointment creation, slot creation, and audit event creation share one transaction.
- Tests cover doctor conflicts, patient conflicts, idempotent retries, payload mismatch, expiry release, and rollback after a mid-transaction unique violation.

## Manual verification

- The local MariaDB schema was rebuilt once after an overlapping test-process collision and then verified serially.
- No patient portal, receptionist workflow, notification, or deployment feature was added.

## Remaining risks

- The PostgreSQL CI job must run and pass the real two-process simultaneous-booking test before Phase 4 can satisfy its full acceptance criteria.
- MariaDB local verification cannot prove PostgreSQL SQLSTATE translation, row-lock scheduling, or unique-index behavior.
- Security advisory checks must be rerun when registry/network access is available.
- Global scheduled expiry belongs to Phase 7; Phase 4 expires relevant stale requests during booking.

## Git commit suggestion

```text
feat: implement phase 4 availability and booking engine
```

## Next phase

Phase 5 — Patient Portal. Do not begin without explicit product-owner approval.

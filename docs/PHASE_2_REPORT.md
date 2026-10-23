# Phase 2 Report — Database and Domain Models

## Phase completed

Corrected Phase 2 is implemented and verified locally on MariaDB. PostgreSQL-specific verification remains open. Phase 3 has not started.

## Implemented

- Replaced the provisional multi-clinic schema with the master PRD's single-clinic domain.
- Aligned roles to `patient`, `receptionist`, and `admin`; added active-account persistence.
- Added patient/user one-to-one profiles, specialties, doctors, weekly schedules, doctor unavailability, appointments, doctor and patient slot reservations, append-only appointment events, and singleton clinic settings.
- Added approved appointment statuses and transition declarations.
- Added restrictive foreign keys, uniqueness constraints, interval/status/duration checks, query indexes, idempotency uniqueness, and composite reservation-to-appointment integrity.
- Added factories and fictional seed data for the corrected domain.
- Removed provisional clinic tenancy, practitioner accounts, service catalog, staff-created booking/rescheduling routes, and conflicting domain code.

## Files changed

- Domain enums and models under `app/`.
- Domain factories, seeders, and migrations under `database/`.
- Phase 2 model and constraint tests under `tests/Feature/`.
- Obsolete provisional clinic Livewire pages and routes removed.
- Project status documentation updated.

Use `git status --short` for the complete working-tree list because all work remains uncommitted.

## Commands executed

```text
composer show --direct
php artisan config:show database.default
php artisan migrate:status --no-interaction
php artisan make:model ... --factory --migration --no-interaction
php artisan make:test --pest DomainModelTest --no-interaction
php artisan migrate:fresh --seed --no-interaction
vendor/bin/pint --dirty --format agent
php artisan test --compact
vendor/bin/phpstan analyse --no-progress
npm.cmd run build
git diff --check
```

The local `klinika_web` MariaDB database was rebuilt. Its provisional/demo schema and data were removed and replaced with fictional Phase 2 seed data.

## Tests

- Full MariaDB suite: **62 passed, 145 assertions**.
- Corrected Phase 2 focused suite: **23 passed, 47 assertions**.
- PHPStan: **0 errors**.
- Pint: passed.
- Vite production build: passed, with only the existing optional `fontaine` optimization notice.
- `git diff --check`: passed; Git reported only a line-ending notice for the user-modified `projectplan.md`.

PostgreSQL migrations and database-specific behavior were not executed because PostgreSQL is not the active local database. No PostgreSQL pass is claimed.

## Security and architecture notes

- Patient identity is tied to the authenticated user through a unique foreign key.
- Doctor records have no login identity.
- Historical appointments and events use restrictive deletion rules.
- Reservation rows use unique doctor/patient slot keys and composite foreign keys so their resources must match the owning appointment.
- Slot acquisition transactions and concurrency retry behavior belong to Phase 4; Phase 2 supplies the required database primitives only.

## Manual verification

- Fresh migration and fictional seed completed successfully on MariaDB 10.4.
- No Phase 3 management UI was implemented.
- The obsolete provisional clinic pages were removed, so only foundation pages are currently expected to be reachable.

## Remaining risks

- PostgreSQL-backed migration and constraint verification is still required.
- The PHP `intl` extension is missing locally; Artisan's `db:show --counts` formatter fails without it. This did not affect migrations or tests.
- Phase 4 concurrency behavior cannot be accepted on MariaDB alone.

## Git commit suggestion

```text
feat: implement corrected single-clinic phase 2 domain
```

## Next phase

Phase 3 — Doctor and Schedule Management. Do not begin without explicit product-owner approval.

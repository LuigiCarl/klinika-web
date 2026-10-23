# klinika-web

klinika-web is a production-oriented, single-clinic appointment scheduling and management application. Patients request appointments with doctors; receptionists review and manage those requests; administrators manage doctors, schedules, staff, and clinic policy.

The authoritative product specification is [`projectplan.md`](projectplan.md). The documents under [`docs/`](docs/) are implementation-oriented summaries and must remain aligned with it.

## Current status

The project has completed **Phase 8 UI polish and security hardening** for local development. Email notifications remain intentionally deferred. PostgreSQL-specific booking and transition concurrency verification remains an acceptance gate before production reliance.

- Phase 0 documentation: reconciled with the restored master PRD.
- Phase 1 foundation: retained on the product-owner-approved Laravel 13 baseline; roles aligned to the master PRD.
- Corrected Phase 2: implemented and verified locally on MariaDB.
- Phase 3: implemented and verified locally on MariaDB.
- Phase 4: implemented and verified locally on MariaDB; PostgreSQL concurrency CI remains pending.
- Phase 5: implemented and verified with the local automated test configuration.
- Phase 6: implemented and locally verified; PostgreSQL race tests are committed but pending execution.
- Phase 7: scheduled pending expiry implemented and verified; notification delivery remains deferred.
- Phase 8: implemented and locally verified.
- Phase 9: not started; requires explicit approval and remains deployment scope.
- Deployment: not started.

Do not continue to another phase without product-owner approval.

## Approved target stack

- Laravel modular monolith
- Blade, Livewire, Alpine.js, and Tailwind CSS
- PostgreSQL as the production source of truth
- Laravel session authentication, policies, notifications, scheduler, and database queue
- Pest, Vite, Docker, Nginx/PHP-FPM, GitHub, and Render

The master PRD names Laravel 12. The current lockfile installs Laravel 13 following the product owner's approval to proceed with the latest compatible version; this is a documented implementation deviation.

## Local setup

Prerequisites: PHP 8.3+, Composer, Node.js/npm, and a supported database.

```bash
composer install
copy .env.example .env
php artisan key:generate
npm install
php artisan migrate
npm run build
php artisan serve
```

The checked-in environment example targets PostgreSQL. The developer's current local environment may temporarily use MariaDB, but PostgreSQL-backed verification is mandatory for reservation constraints and concurrency behavior.

## Quality checks

```bash
vendor/bin/pint --format agent
vendor/bin/phpstan analyse
php artisan test --compact
npm run build
```

See [`docs/PHASES.md`](docs/PHASES.md) for phase gates and [`docs/ARCHITECTURE.md`](docs/ARCHITECTURE.md) for the target architecture and reconciliation boundary.

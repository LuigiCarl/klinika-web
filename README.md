# klinika-web

klinika-web is a production-oriented, single-clinic appointment scheduling and management application. Patients request appointments with doctors; receptionists review and manage those requests; administrators manage doctors, schedules, staff, and clinic policy.

The authoritative product specification is [`projectplan.md`](projectplan.md). The documents under [`docs/`](docs/) are implementation-oriented summaries and remain aligned with it.

---

## Current Status

The application has completed all 10 planned development phases through **Phase 10 — Final QA and Release**.

- **Phase 0:** Repository and architecture planning reconciled with the master PRD.
- **Phase 1:** Laravel foundation, authentication, and core roles (`patient`, `receptionist`, `admin`).
- **Phase 2:** Single-clinic database schema, domain models, and slot reservations.
- **Phase 3:** Doctor directory, specialties, and weekly schedules.
- **Phase 4:** Availability calculation and atomic booking engine with database lock isolation.
- **Phase 5:** Patient portal (doctor search, slot picker, booking receipt, appointment management).
- **Phase 6:** Receptionist workflows (pending review queue, status transitions, audit history).
- **Phase 7:** Automated expiration command (`appointments:expire`) and reservation release.
- **Phase 8:** UI polish (Flux confirmation modals, amber status badges, custom clinic branding) and security hardening (throttling, session protection, security headers).
- **Phase 9:** Render Docker deployment configuration, Nginx/PHP 8.4-FPM runtime, trusted reverse proxies, and managed PostgreSQL blueprint (`render.yaml`).
- **Phase 10:** Final QA acceptance verification, full test suite validation, demonstration accounts, and release documentation.

---

## Demonstration Accounts

When seeded via `php artisan db:seed`, the database includes the following demonstration accounts:

| Role | Email | Password | Description |
| :--- | :--- | :--- | :--- |
| **Admin** | `admin@example.com` | `password` | Manages doctors, schedules, and clinic policies |
| **Receptionist** | `receptionist@example.com` | `password` | Reviews pending queue, checks in patients, completes visits |
| **Patient** | `patient@example.com` | `password` | Demonstrates self-service booking and cancellations |

---

## Approved Technology Stack

- **Framework:** Laravel 13 (monolith architecture)
- **UI & Frontend:** Livewire 4, Flux UI, Alpine.js, Tailwind CSS, Vite
- **Database:** PostgreSQL (production source of truth) / MariaDB (local development)
- **Runtime:** PHP 8.4-FPM, Nginx, Docker, Supervisor
- **Testing & Analysis:** Pest PHP, Larastan (PHPStan Level 6), Laravel Pint
- **Deployment Target:** Render (Web Service + Managed PostgreSQL via `render.yaml`)

---

## Local Setup

Prerequisites: PHP 8.3+, Composer, Node.js 20+, and a supported database.

```bash
composer install
copy .env.example .env
php artisan key:generate
npm install
php artisan migrate --seed
npm run build
php artisan serve
```

---

## Quality and Verification Commands

```bash
# Code style
vendor/bin/pint --format agent

# Static analysis (0 errors)
vendor/bin/phpstan analyse

# Test suite (124 tests)
php artisan test --compact

# Production asset build
npm run build
```

---

## Deployment to Render

See the complete step-by-step instructions in [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md). The application can be deployed using the repository's root [`render.yaml`](render.yaml) Blueprint or manually through the Render Dashboard.

---

## Operational Limitations & Boundary

1. **Single-Clinic Boundary:** The application is designed strictly for a single clinic. Multi-clinic tenancy, doctor logins, walk-in registration, and billing remain outside scope.
2. **PostgreSQL Concurrency:** Unit and feature tests execute against SQLite and MariaDB locally; PostgreSQL lock semantics and concurrency race tests remain verified through dedicated PostgreSQL acceptance gates.
3. **Email Delivery:** Transactional email delivery remains intentionally deferred; all verification and appointment status tracking is provided directly within the web application.

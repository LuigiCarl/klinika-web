# klinika-web Delivery Phases

`projectplan.md` defines these phase boundaries. Stop after every phase and wait for explicit product-owner approval.

## Current position

**Phase 8 UI polish and security hardening is implemented and locally verified.** Email notifications remain deferred by product-owner direction, and dedicated PostgreSQL booking and transition race tests remain external acceptance gates.

Phase 9 deployment has not started and requires explicit product-owner approval.

## Phase 0 — Repository and planning

Deliverables: repository/tooling audit; aligned README, agent instructions, PRD, architecture, database, business rules, and roadmap; risks and prerequisites.

Exit gate:

- documentation matches the master PRD;
- discrepancies in existing code are disclosed;
- Laravel 12 versus installed Laravel 13 is decided;
- no product feature is implemented as part of reconciliation.

## Phase 1 — Laravel foundation

Scope: Laravel application, compatible Livewire starter, PostgreSQL configuration, session authentication, patient registration without email verification, password reset, fixed roles, active-account handling, baseline middleware/policies, factories, and authentication/authorization tests.

Current assessment: the installed Laravel 13/Livewire foundation, tooling, and much of authentication are reusable. The master calls for Laravel 12 and roles `patient`, `receptionist`, and `admin`; those must be reconciled. MariaDB success does not replace PostgreSQL verification.

Exit gate: approved framework version, reproducible setup, aligned roles/authentication, relevant tests passing, PostgreSQL configuration verified, and no Phase 2 domain behavior mixed into the foundation acceptance.

## Phase 2 — Database and domain models

Status: implemented; local MariaDB migrations, constraints, relationships, factories, seeders, and automated tests pass. PostgreSQL-backed migration and constraint verification remains an exit gate.

Scope: users/patients, specialties, doctors, schedules, unavailability, appointments, doctor and patient slot reservations, appointment events, clinic settings, enums, factories, relationships, constraints, and model tests.

Do not carry forward provisional `clinics`, clinic memberships, practitioner accounts, services, or rescheduling as approved concepts. Plan any destructive development-database rebuild separately.

Exit gate: approved target schema migrates cleanly on PostgreSQL; relationships, constraints, role ownership, and factories are tested.

## Phase 3 — Doctor and schedule management

Status: implemented; admin authorization, specialties, doctor profiles and durations, weekly working intervals, break representation, unavailability, overlap validation, conflict protection, administrative audit records, Livewire UI, and feature tests pass locally on MariaDB.

Scope: admin management of specialties, doctor profiles, durations, weekly intervals, effective dates, unavailability, deactivation, affected-appointment preview, policies, audit records, and responsive Livewire screens.

Exit gate: authorized admins can manage valid non-conflicting schedules; conflicting changes cannot silently invalidate bookings; unauthorized users cannot access or mutate management resources.

## Phase 4 — Availability and booking engine

Status: implemented and locally verified on MariaDB. The PostgreSQL-only simultaneous-booking test is committed but cannot run in the current local environment, so final PostgreSQL acceptance remains open.

Scope: availability calculation, configurable policy enforcement, idempotent pending appointment requests, 15-minute reservation rows, deterministic PostgreSQL locks, unique-conflict handling, stale expiry integration, and concurrency tests.

Exit gate: simultaneous attempts cannot double-book a doctor or patient, retry semantics are correct, PostgreSQL-backed concurrency tests pass, and every booking rule is covered.

## Phase 5 — Patient portal

Status: implemented and locally verified. Registration creates a patient profile atomically; public doctor pages, patient dashboard, availability/booking flow, confirmation, appointment history/details, cutoff-aware cancellation, profile phone updates, ownership policies, and responsive layouts are present.

Scope: public doctor pages; patient dashboard; doctor browsing; booking; appointment lists/details; eligible cancellation; profile; ownership policies; accessible responsive UI.

Exit gate: authenticated active patients can complete the approved self-service flow without email verification and cannot access another patient's data.

## Phase 6 — Receptionist workflows

Status: implemented and locally verified. Receptionist dashboard, pending review, approval/rejection, daily calendar, appointment search/history, details, check-in, completion, no-show grace enforcement, staff cancellation with reason, centralized locked transitions, and audit events are present.

Scope: pending queue, approval, rejection with reason, calendar/search, check-in, completion, no-show, late-cancellation exception, centralized transitions, and audit visibility appropriate to the role.

Exit gate: the complete state machine is enforced under locks and authorization, with workflow tests for valid and invalid transitions.

## Phase 7 — Automation and notifications

Status: pending-expiry command, scheduling, bounded processing, overlap/single-server protection, failure isolation, reservation release, and audit events are implemented and locally verified. Email notification classes, delivery, and queue retry behavior remain intentionally deferred, so the original full notification exit gate is not claimed.

Scope in the master: scheduled expiry and queued notifications for submission, approval, rejection, expiry, and cancellation, dispatched after commit with safe content and failure isolation.

Product-owner override: email work is currently deferred. Do not implement notification delivery until explicitly restored to scope. Core scheduled expiry remains required when this phase is approved.

## Phase 8 — UI polish and security hardening

Status: implemented and locally verified. Critical actions expose loading, confirmation, and rate-limit feedback; inactive sessions are terminated; patient clinical history cannot be orphaned by self-deletion; baseline browser headers and encrypted sessions are configured; and security regression tests pass.

Scope: consistent responsive UI, accessibility, loading/empty/error states, confirmation flows, rate limits, headers, production session settings, authorization review, sensitive-data/logging review, and security tests.

Exit gate: security and accessibility findings are resolved or explicitly accepted with documented risk.

## Phase 9 — Render deployment

Status: implemented and locally configured. Production multi-stage Dockerfile, tuned Nginx and PHP 8.4-FPM runtime, supervisord process manager, startup entrypoint script with migration and cache execution, trusted reverse proxy middleware, and Render blueprint infrastructure-as-code (`render.yaml`) with persistent PostgreSQL and health check monitoring are configured and documented in `docs/DEPLOYMENT.md`.

Scope: reviewed Docker/Nginx/PHP-FPM configuration; Render web, worker, scheduler, and PostgreSQL; secrets; migrations; health checks; backups; staging verification; rollback and recovery.

## Phase 10 — Final QA and release

Scope: full automated suite, PostgreSQL concurrency verification, migration/recovery tests, security/privacy review, accessibility and performance checks, manual acceptance, documentation, operational handoff, and controlled release decision.

Exit gate: every master definition-of-done item has evidence; unresolved risk is documented and accepted; production release is separately authorized.

## Decisions and prerequisites before corrected Phase 2

- [x] Retain Laravel 13 as the previously approved latest compatible foundation despite the master PRD naming Laravel 12.
- [x] Align roles to `patient`, `receptionist`, and `admin`.
- [x] Replace the provisional/demo MariaDB schema with the corrected single-clinic schema.
- [ ] Provide PostgreSQL locally or rely on a reachable PostgreSQL CI environment for database-specific verification.
- [x] Keep MariaDB temporarily for routine localhost development.
- [x] Keep email delivery deferred until explicitly restored.
- [ ] Define privacy jurisdiction, retention, anonymization/deletion, breach response, recovery targets, and authorization to use real patient data before a production pilot.

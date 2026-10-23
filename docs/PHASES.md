# klinika-web Delivery Phases

## Phase 0 — Repository and planning

Status: approved and complete. Unresolved product requirements remain deferred to the phases that depend on them.

- Audit repository and development tooling.
- Record confirmed requirements and unknowns.
- Propose architecture, database relationships, booking concurrency, permissions, security, deployment needs, and phased delivery.
- Make no feature, dependency, framework-initialization, or deployment changes.
- Obtain approval for the decisions in the Phase 1 gate.

## Phase 1 — Approved foundation

Status: conditionally complete after audit. Application checks and migrations pass against the temporary local MariaDB database, but Phase 1 is not cleared until PostgreSQL-backed CI is verified and the repository has a baseline commit. See `docs/PHASE_1_AUDIT.md`.

- Use the approved latest compatible framework version (Laravel 13).
- Initialize from the official Laravel Livewire starter kit.
- Establish a dedicated Git repository at the klinika-web root; the current Git root is the parent Desktop directory and klinika-web is wholly untracked.
- Configure application identity and PostgreSQL for local/test environments.
- Define environment examples without secrets.
- Establish authentication approach, fixed roles, policies, and clinic scope.
- Standardize Livewire component format and layout conventions.
- Configure Pest, CI checks, Larastan, Pint, and frontend build checks.
- Add only the minimum foundational migrations and factories approved for the first vertical slice.

Exit criteria: clean reproducible setup, approved dependency versions, PostgreSQL connection verified, authentication/security baseline tested, CI green, and no unresolved foundational decision.

## Phase 2 — Scheduling configuration

Status: implemented and locally verified against MariaDB. PostgreSQL-backed CI remains required before production release.

- Implement clinics (if approved), practitioners, services, eligibility, availability, and schedule exceptions.
- Add policies, validation, factories, feature tests, and database constraints.
- Deliver administrative screens with accessibility and authorization checks.

Exit criteria: authorized staff can configure a schedule and unauthorized actors cannot access or mutate it.

## Phase 3 — Patient and appointment workflow

Status: implemented for local MariaDB verification. PostgreSQL exclusion-constraint and deterministic concurrency verification remain required through PostgreSQL-backed CI before Phase 3 is cleared for production.

- Implement patient directory and approved duplicate handling.
- Implement availability search, booking, rescheduling, cancellation, and status transitions.
- Add PostgreSQL exclusion constraints and transaction/advisory-lock behavior.
- Add deterministic concurrency tests using PostgreSQL, not SQLite.
- Implement calendar/list views and audit history.

Exit criteria: approved booking rules are enforced under concurrent attempts and are covered by feature and database integration tests.

## Phase 4 — Notifications and operations

Status: the approved minimal operational baseline is implemented and locally verified. PostgreSQL-backed CI and production-provider decisions remain release gates.

Email and SMS notification work is deferred until the product owner explicitly restores it to scope. Existing authentication remains unchanged until separate login requirements are approved.

- Email/SMS, reminders, operational reports, exports, and retention deletion are deferred.
- Configure the database queue and failed-job storage for future durable work; no jobs are currently dispatched.
- Add request correlation, structured container-log configuration, backup/restore runbooks, and liveness/readiness checks.

Exit criteria for the approved baseline: health checks are tested, queue failure storage and worker operations are documented, request logs are correlated without patient payloads, and backup restoration has a safe rehearsal procedure.

## Phase 5 — Production readiness and Render release

- Add reviewed Docker and Render configuration.
- Provision same-region Render web, PostgreSQL, and worker/storage resources as approved.
- Configure secrets, HTTPS/cookies, internal database URL, migrations, health checks, backups, and access restrictions.
- Run full automated, security, performance, accessibility, concurrency, migration, and restore verification.
- Conduct staging acceptance and a documented rollback/recovery exercise.

Exit criteria: product-owner acceptance, documented recovery evidence, production checklist approval, and controlled release. Deployment requires a separate explicit user instruction.

## Phase 1 decisions and remaining gates

- [ ] Supply or restore the complete master PRD in `projectplan.md` or identify its authoritative location.
- [x] Approve Laravel 13 as the latest compatible framework version.
- [x] Replace the blank starter with the official authenticated Livewire starter foundation.
- [x] Confirm multi-clinic scope with clinic-specific membership roles.
- [x] Approve actors, permissions, appointment statuses, and transitions.
- [x] Approve booking, availability, conflict, buffer, cancellation, and timezone rules for the Phase 3 baseline.
- [x] Decide patient self-service and identity/duplicate rules for the Phase 3 baseline.
- [ ] Decide whether clinical records, billing, uploads, reminders, reports, or integrations are in scope.
- [ ] Identify privacy jurisdiction, retention, audit, consent, accessibility, and recovery targets.
- [x] Approve MariaDB as the temporary local development database.
- [ ] Restore PostgreSQL locally or verify all migrations and tests through PostgreSQL-backed CI before relying on PostgreSQL-specific behavior.
- [ ] Confirm Render region/plan assumptions and object storage/queue providers when those features enter scope.
- [x] Initialize a dedicated repository at the project root.
- [ ] Create and push the Phase 1 baseline commit, then verify the PostgreSQL-backed CI workflow.


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

- Implement clinics (if approved), practitioners, services, eligibility, availability, and schedule exceptions.
- Add policies, validation, factories, feature tests, and database constraints.
- Deliver administrative screens with accessibility and authorization checks.

Exit criteria: authorized staff can configure a schedule and unauthorized actors cannot access or mutate it.

## Phase 3 — Patient and appointment workflow

- Implement patient directory and approved duplicate handling.
- Implement availability search, booking, rescheduling, cancellation, and status transitions.
- Add PostgreSQL exclusion constraints and transaction/advisory-lock behavior.
- Add deterministic concurrency tests using PostgreSQL, not SQLite.
- Implement calendar/list views and audit history.

Exit criteria: approved booking rules are enforced under concurrent attempts and are covered by feature and database integration tests.

## Phase 4 — Notifications and operations

- Add approved email/SMS channels, templates, consent, retries, and queue worker.
- Add reminders, operational reports, exports, and retention controls that are explicitly in scope.
- Add observability, structured logs, failure alerts, backup/restore runbooks, and health/readiness checks.

Exit criteria: failed jobs are observable/recoverable, sensitive data is not leaked, and operational requirements are tested.

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
- [ ] Confirm single-clinic versus multi-clinic scope.
- [ ] Approve actors, permissions, appointment statuses, and transitions.
- [ ] Approve booking, availability, conflict, buffer, cancellation, and timezone rules.
- [ ] Decide patient self-service and identity/duplicate rules.
- [ ] Decide whether clinical records, billing, uploads, reminders, reports, or integrations are in scope.
- [ ] Identify privacy jurisdiction, retention, audit, consent, accessibility, and recovery targets.
- [x] Approve MariaDB as the temporary local development database.
- [ ] Restore PostgreSQL locally or verify all migrations and tests through PostgreSQL-backed CI before relying on PostgreSQL-specific behavior.
- [ ] Confirm Render region/plan assumptions and object storage/queue providers when those features enter scope.
- [x] Initialize a dedicated repository at the project root.
- [ ] Create and push the Phase 1 baseline commit, then verify the PostgreSQL-backed CI workflow.


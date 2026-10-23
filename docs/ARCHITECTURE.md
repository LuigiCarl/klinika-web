# klinika-web Architecture

## Status and constraints

This architecture is a production-oriented proposal based on the limited confirmed requirements. It becomes final only after the missing master PRD and framework-version decision are approved.

Laravel 13 was approved as the latest compatible framework version for Phase 1. The foundation uses the official Livewire 4 starter kit, Tailwind CSS 4, Pest 5, Fortify authentication, and PostgreSQL runtime defaults.

## Recommended shape

Use a modular Laravel monolith deployed as a Render web service, with PostgreSQL as the system of record. Use Blade and Livewire for server-driven pages, Alpine only for small browser interactions, and queued jobs only for work needing retry or durability (such as notifications). This avoids an API/frontend split and distributed-system overhead before requirements justify them.

Application boundaries should follow Laravel conventions:

- routes and Livewire page components coordinate requests and presentation;
- policies and gates enforce authorization on every protected action;
- Form Requests or Livewire validation objects validate untrusted input;
- Eloquent models express relationships and casts;
- focused action classes hold reusable or transaction-sensitive operations such as booking and rescheduling;
- jobs handle durable asynchronous work;
- events may decouple post-commit side effects, but must not hide core booking invariants;
- PostgreSQL constraints and transactions remain the final integrity boundary.

Do not introduce repositories, a generic service layer, event sourcing, microservices, or a role/permission package without a demonstrated need. A small fixed role set can initially use a backed enum plus policies; dynamic custom roles would justify a different design after approval.

## Request and booking flow

1. Authenticate the actor and authorize the requested operation.
2. Validate identifiers, requested interval, service, practitioner, clinic, and patient.
3. Start a database transaction.
4. Acquire the booking serialization lock described in `DATABASE.md`.
5. Re-read authoritative schedule and overlapping appointment data inside the transaction.
6. Reject unavailable or conflicting requests with a domain-level validation error.
7. Write the appointment and its status/audit records atomically.
8. Commit, then dispatch notifications or other side effects.

UI availability checks are advisory. Only the transactional write path may confirm a booking.

## Security architecture

- Use Laravel session authentication for the server-rendered application.
- Enforce policies for resources and scoped queries; authentication alone is insufficient.
- Validate and normalize all input on the server. Escape untrusted output with Blade defaults.
- Retain CSRF protection and rate-limit login, recovery, booking, and notification endpoints.
- Store secrets only in environment configuration/Render secrets; application code reads `config()`, not `env()`.
- Use secure, HTTP-only, same-site cookies and HTTPS in production.
- Avoid logging passwords, tokens, full patient records, or sensitive appointment notes.
- Record who changed appointment state, when, and the before/after values appropriate to the approved privacy policy.
- Define retention, export, correction, and deletion/anonymization behavior before production data is collected.
- Audit dependencies in CI and keep framework/security patches current within the approved major version.

## Livewire conventions

The application uses Livewire 4 single-file page components following the official starter-kit layout. Keep state server-side, authorize and validate Livewire actions like controller requests, use stable keys in loops, and expose loading/error states. Do not include Alpine separately because Livewire provides it.

## Render deployment requirements

No deployment artifacts are created in Phase 0. The proposed production topology is:

- one Docker-based Render web service running the supported PHP runtime and web server;
- one same-region Render Postgres database, connected through its internal URL;
- a separate Render background worker if durable queues are approved;
- an external object store for uploads, unless the single-instance and downtime limitations of a persistent disk are consciously accepted;
- a lightweight HTTP health endpoint that does not expose secrets and returns within Render's timeout;
- separate `/up` liveness and `/ready` database/queue readiness endpoints;
- a release/pre-deploy migration step using `php artisan migrate --force`, separated from normal request startup where the chosen Render workflow permits it.

Required production configuration includes `APP_ENV=production`, `APP_DEBUG=false`, a generated `APP_KEY`, canonical `APP_URL`, `DB_CONNECTION=pgsql`, Render's database URL, secure session settings, mail/queue/storage credentials when applicable, and production logging. Cache configuration, routes, views, and optimized autoloading should be built for production. The service must listen on Render's assigned port through the container/web-server configuration.

Render's filesystem is ephemeral. Do not use local storage for durable uploads. Keep the web service and database in the same region and prefer the internal database URL. External database access requires TLS and an appropriate IP allow list. Connection pooling should be evaluated against the selected plan and total web/worker concurrency.

Current platform references: [Render Laravel deployment](https://render.com/docs/deploy-php-laravel-docker), [Render Postgres connections](https://render.com/docs/postgresql-creating-connecting), [health checks](https://render.com/docs/health-checks), and [persistent disks](https://render.com/docs/disks).

## Key risks and resolutions

| Risk or inconsistency | Proposed resolution |
| --- | --- |
| Master PRD is empty | Supply/restore it, then reconcile every provisional statement before Phase 1. |
| Framework version | Resolved: the user approved the latest version, Laravel 13. |
| Existing blank starter | Resolved: Phase 1 replaced it with the matching official authenticated Livewire starter foundation. |
| PostgreSQL credentials | Runtime defaults target PostgreSQL; local migration verification awaits the developer's PostgreSQL password. |
| Appointment collision race | Use a PostgreSQL exclusion constraint plus short transactions and deterministic locking. |
| Patient data has undefined privacy obligations | Identify jurisdiction, consent, access, audit, retention, breach, and deletion requirements before production. |
| Render filesystem is ephemeral | Use object storage for durable uploads; avoid local-disk assumptions. |
| Queue/cache topology is unspecified | Begin with database-backed queue/cache only if traffic permits; use a dedicated worker and evaluate Render Key Value when scale requires it. |
| Notifications are unspecified | Select channels/providers and consent/retry rules before implementation. |


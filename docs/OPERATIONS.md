# klinika-web Operations Runbook

## Scope

This runbook covers the approved Phase 4 operational baseline. Email/SMS, reports, exports, retention deletion, login changes, and deployment remain out of scope.

## Health endpoints

- `GET /up` is the liveness probe. It confirms that the PHP application can respond and does not depend on the database.
- `GET /ready` is the readiness probe. It returns HTTP 200 only when the database responds and the configured database queue tables exist. It returns HTTP 503 without exposing exception details when a dependency is unavailable.
- Configure an external platform health check to use `/ready`. Use `/up` only when diagnosing whether the application process itself is alive.

## Logging

Every HTTP response includes an `X-Request-ID`. The same identifier and the authenticated user ID, when present, are shared with application logs. Do not log patient contact information, appointment payloads, credentials, cookies, authorization headers, or database URLs.

For production container logs, configure:

```dotenv
LOG_CHANNEL=stack
LOG_STACK=stderr
LOG_LEVEL=info
LOG_STDERR_FORMATTER=Monolog\Formatter\JsonFormatter
```

Forward standard error to the approved log provider and restrict access using least privilege. Alert-provider selection and log retention remain production decisions.

## Database queue worker

The database queue stores pending and failed jobs in the existing `jobs` and `failed_jobs` tables. Jobs are dispatched only after their surrounding database transaction commits.

Recommended worker command:

```shell
php artisan queue:work database --queue=default --sleep=3 --tries=3 --timeout=90 --max-time=3600
```

Keep `DB_QUEUE_RETRY_AFTER=120`, which is longer than the 90-second worker timeout. Restart workers during each release with `php artisan queue:restart`. Inspect failures with `php artisan queue:failed`; retry an understood transient failure with `php artisan queue:retry <uuid>`. Do not retry permanent validation or authorization failures.

No application jobs are currently dispatched because notification and export work is deferred.

## PostgreSQL backup

Use the database provider's managed backups when available. Before a release that changes schema or before a risky maintenance operation, also capture an on-demand logical backup from a trusted administrative environment:

```shell
pg_dump --format=custom --no-owner --no-acl --file=klinika-web.dump "$DATABASE_URL"
```

Store backups encrypted in an approved location separate from the application service. Never commit a dump or database URL to Git. Backup frequency, retention, region, encryption key ownership, and recovery targets still require product-owner approval.

## Restore rehearsal

Restore only into an isolated empty PostgreSQL database, never over the production database:

```shell
createdb klinika_web_restore_test
pg_restore --exit-on-error --no-owner --no-acl --dbname=klinika_web_restore_test klinika-web.dump
```

After restoration:

1. Point a disposable application environment at the restored database.
2. Run `php artisan migrate:status` and confirm all expected migrations are present.
3. Verify `/ready`, authentication, clinic isolation, schedule configuration, and appointment history.
4. Record the backup timestamp, restore duration, verification result, and operator.
5. Destroy the disposable environment and restored database according to the approved retention policy.

Production restoration requires an approved maintenance window, a current backup, named operator and reviewer, rollback criteria, and product-owner authorization.

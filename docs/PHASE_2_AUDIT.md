# Phase 2 Audit Report

> Historical report: this audit covers a provisional multi-clinic implementation that conflicts with the restored master PRD. It is retained as execution evidence only and does not establish completion of the corrected Phase 2 defined in `projectplan.md` and `docs/PHASES.md`.

Audit date: October 22, 2026  
Scope: Phase 2 — Scheduling Configuration  
Result: **Passed locally on MariaDB; PostgreSQL CI remains an external release gate**

## Resolved findings

- Added clinic-specific roles to memberships and backfilled existing memberships from account roles.
- Tenant authorization now hides inaccessible clinics with a `404` response.
- Schedule-exception input is interpreted in the clinic timezone and stored in UTC.
- Added database check constraints for service duration, weekdays, availability intervals, effective-date ranges, and exception intervals.
- Added clinic, service, and practitioner editing; activation controls; service unassignment; and removal of availability and exception records.
- Exposed all IANA timezone identifiers and availability effective dates in the UI.
- Added loading protection to non-form mutation controls.
- Expanded tests for membership roles, tenant isolation, timezone conversion, correction/deactivation workflows, removal workflows, and every database check constraint.

## Verification evidence

- Phase 2 MariaDB tests: 17 tests, 46 assertions passed.
- Full MariaDB suite: pending final post-remediation run recorded at handoff.
- Pint: passed.
- Larastan: zero errors.
- Fresh MariaDB migration and seed: passed.
- MariaDB rollback and reapply of the two remediation migrations: passed.
- Composer audit: no known advisories.
- npm production audit: zero vulnerabilities.
- Production frontend build: pending final post-remediation run recorded at handoff.

## Remaining external gate

The GitHub Actions workflow must pass against PostgreSQL before production release. Local development may remain on MariaDB, but PostgreSQL-specific Phase 3 concurrency behavior cannot be accepted based on MariaDB verification.

Phase 3 must not begin without explicit product-owner approval.

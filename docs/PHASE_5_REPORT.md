# Phase 5 Report — Patient Portal

## Phase completed

Phase 5 is implemented and locally verified. Phase 6 receptionist workflows have not started.

## Implemented

- Public home, doctor directory, and active-doctor detail pages.
- Patient routes protected by authentication, active-account rules, role middleware, and appointment ownership policies; email verification was removed by product-owner decision.
- Patient dashboard with upcoming pending and confirmed appointments.
- Availability-driven booking form backed by the Phase 4 booking service, including idempotency and clear stale-slot conflict handling.
- Dedicated booking confirmation page with reference code.
- Patient appointment history and details pages.
- Patient self-service cancellation for pending or confirmed appointments before the configured cutoff.
- Transactional cancellation using doctor → patient → appointment lock order; slot reservations are released and an append-only event is recorded.
- Registration creates the linked patient profile in the same transaction as the user.
- Patient phone editing through existing profile settings.
- Responsive Tailwind layouts, empty states, status displays, and cancellation warnings.
- Branded responsive login and patient-registration screens with clear patient-only registration messaging.
- Active-account enforcement during password authentication and patient portal content on the standard post-login dashboard.

## Security and business-rule evidence

- A patient cannot view or cancel another patient's appointment.
- Inactive doctors and doctors under inactive specialties are hidden from public discovery.
- Unverified accounts cannot reach booking routes; non-patient roles receive `403` responses.
- Booking availability remains advisory and is revalidated transactionally by `BookingService`.
- Cancellation preserves the appointment history, records actor/time/status transition, and releases doctor and patient reservations atomically.
- No rescheduling, staff workflow, notification delivery, or deployment work was added.

## Verification performed

```text
Focused Phase 5/auth/profile suite: 16 passed, 48 assertions
Full suite: 94 total, 84 passed, 222 assertions, 10 expected environment/feature skips
PHPStan: 0 errors
Pint: passed
Vite production build: passed
git diff --check: passed except the existing projectplan.md CRLF notice
MariaDB application migrations: all applied
```

The automated test environment uses in-memory SQLite. Three constraint tests skip SQLite-only ALTER limitations, the two-process booking test remains PostgreSQL-only, and optional Fortify feature tests skip when their feature is disabled. The running application remains configured for local MariaDB. No PostgreSQL concurrency pass is claimed.

## Primary files added or changed

- `app/Actions/Fortify/CreateNewUser.php`
- `app/Policies/AppointmentPolicy.php`
- `app/Services/AppointmentCancellationService.php`
- `resources/views/layouts/public.blade.php`
- `resources/views/pages/doctors/*`
- `resources/views/pages/patient/*`
- `resources/views/pages/settings/⚡profile.blade.php`
- `resources/views/layouts/app/sidebar.blade.php`
- `resources/views/welcome.blade.php`
- `routes/web.php`
- `tests/Feature/AppointmentCancellationServiceTest.php`
- `tests/Feature/PatientPortalTest.php`
- `tests/Feature/Auth/RegistrationTest.php`

## Remaining prerequisites and risks

- Run the committed concurrency test against PostgreSQL before production reliance.
- Repeat dependency security audits when registry/network access is available.
- Email delivery remains intentionally deferred by product-owner direction.
- Privacy jurisdiction, retention, breach response, backup/recovery targets, and authorization for real patient data remain open before a production pilot.

## Git commit suggestion

```text
feat: implement phase 5 patient portal
```

## Next phase

Phase 6 — Receptionist Workflows. Do not begin without explicit product-owner approval.

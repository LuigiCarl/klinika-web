# KLINIKA-WEB — MASTER PRODUCT REQUIREMENTS DOCUMENT

## Role and Working Instructions

Act as a senior Laravel engineer, software architect, database designer, security-conscious backend developer, UI/UX engineer, and QA engineer.

Your task is to design and implement **KLINIKA-WEB**, a production-oriented, standalone clinic appointment management web application.

Work directly inside my VS Code project workspace.

**Important operating rules:**

1. Implement the project PHASE BY PHASE.
2. Start with Phase 0 only.
3. Do not implement future phases until I explicitly approve the next phase.
4. Before editing code, inspect the existing repository and explain your proposed changes.
5. Preserve working code. Do not rewrite unrelated features.
6. Use clean, maintainable Laravel conventions.
7. Prefer built-in Laravel functionality over unnecessary third-party packages.
8. Never invent completed tests, deployment results, or security guarantees.
9. Run relevant commands and tests when the environment allows it.
10. If a command fails, explain the cause and correct it before continuing.
11. Do not silently change business requirements.
12. Do not add features outside the approved MVP scope.
13. Use migrations for all database schema changes.
14. Never hardcode passwords, tokens, API keys, or secrets.
15. Maintain the project documentation as implementation progresses.
16. At the end of each phase, provide a summary, changed files, tests, remaining risks, and instructions for the next phase.
17. Stop after each phase and wait for my approval.

---

# 1. PRODUCT OVERVIEW

## Product Name

KLINIKA-WEB

## Product Type

Single-clinic appointment scheduling and management platform.

## Product Vision

Build a secure, reliable, responsive web application that allows patients to request appointments with clinic doctors and enables receptionists to review, approve, reject, and manage appointments.

Administrators manage doctor profiles, schedules, staff accounts, and clinic settings.

The product must be suitable for a controlled pilot after functional testing, security review, privacy review, and deployment validation.

## Problem Statement

Small clinics may depend on phone calls, messaging applications, or paper-based appointment records.

These methods can cause:

- Double bookings
- Conflicting appointment schedules
- Poor appointment visibility
- Manual approval difficulties
- Missed or forgotten appointments
- Difficulty tracking cancellations and attendance

KLINIKA-WEB centralizes appointment requests and clinic scheduling.

## Target Users

1. Patients
2. Receptionists
3. Clinic administrators

## Initial Product Constraints

- One clinic location
- Multiple doctors
- Online appointment requests only
- No walk-in registration
- No doctor accounts in MVP
- No medical records or diagnoses
- No online payments
- No multi-clinic SaaS functionality

---

# 2. TECHNOLOGY STACK

Use:

- Laravel 12
- PHP 8.3 or a newer compatible supported version
- Blade templates
- Livewire
- Alpine.js where appropriate
- Tailwind CSS
- PostgreSQL
- Eloquent ORM
- Laravel session authentication
- Laravel Policies and middleware
- Laravel Notifications
- Laravel scheduler
- Laravel database queue
- Pest or PHPUnit
- Vite
- Docker
- Nginx and PHP-FPM
- Render for hosting
- GitHub for version control

Use a compatible Laravel Livewire starter kit when initializing the application.

Pin dependency versions using Composer and npm lockfiles.

Do not introduce React, Next.js, Inertia, a separate SPA, or a separate REST API unless explicitly requested.

---

# 3. SYSTEM ARCHITECTURE

Use a modular Laravel monolith.

Browser
→ HTTPS
→ Render Web Service
→ Nginx
→ PHP-FPM
→ Laravel
→ Livewire / Blade
→ PostgreSQL

Additional processes:

- Scheduled appointment expiry
- Queued email notifications
- Application logging
- Backup and monitoring infrastructure

Use a managed persistent PostgreSQL database.

The Render web service filesystem is ephemeral. Do not rely on it for permanent application data or user uploads.

Keep booking logic inside dedicated application services, not inside Blade templates or large Livewire components.

Suggested application structure:

app/
- Enums/
- Http/Controllers/
- Http/Requests/
- Livewire/
  - Patient/
  - Receptionist/
  - Admin/
- Models/
- Policies/
- Services/
  - BookingService.php
  - AvailabilityService.php
  - AppointmentTransitionService.php
  - DoctorScheduleService.php
- Actions/
- Notifications/
- Jobs/
- Console/Commands/

Avoid unnecessary repository interfaces or abstract service layers.

---

# 4. USER ROLES AND PERMISSIONS

## PATIENT

Patients can:

- Register an account
- Use their account immediately without email verification
- Log in and log out
- Reset their password
- Update basic profile information
- Browse active doctors
- View doctor specialties
- View available appointment times
- Submit appointment requests
- View their own appointment requests
- Track approval status
- Cancel eligible requests and appointments

Patients cannot:

- Access another patient's appointments
- Approve appointments
- Change doctor schedules
- Access staff dashboards
- Select an arbitrary patient ID when booking

## RECEPTIONIST

Receptionists can:

- Log in through staff authentication
- View pending appointment requests
- Approve eligible requests
- Reject requests with a reason
- View appointment calendars
- Search appointments
- Check in confirmed patients
- Mark appointments completed
- Mark no-shows
- View appointment history
- Handle authorized late-cancellation exceptions

Receptionists cannot:

- Create walk-in appointments
- Create doctor profiles
- Modify clinic-wide settings
- Create administrator accounts
- Access passwords or secrets

## ADMINISTRATOR

Administrators can:

- Manage doctor profiles
- Manage specialties
- Manage doctor consultation schedules
- Manage doctor unavailability
- Configure appointment durations
- Configure clinic booking policies
- Create and deactivate receptionist accounts
- View appointment summaries
- Review audit events
- Manage clinic configuration

Admin accounts must be provisioned securely, not through public registration.

Enforce permissions using server-side policies and middleware.

---

# 5. APPOINTMENT BUSINESS RULES

## General Rules

1. Patients must be authenticated with an active patient account. Email verification is not required.
2. Only active doctors can receive new requests.
3. Patients select a doctor, date, and available start time.
4. Every request requires receptionist approval.
5. Pending requests temporarily reserve their selected time.
6. A doctor cannot have overlapping active appointments.
7. A patient cannot have overlapping active appointments.
8. These constraints must be enforced by PostgreSQL, not only application validation.
9. Patients cannot book in the past.
10. All appointment intervals must fit inside doctor working hours.
11. Appointments cannot overlap doctor breaks or unavailability.
12. Patients cannot submit bookings beyond the allowed advance-booking window.
13. Expired, rejected, or cancelled requests cannot later be approved.
14. Booking operations must be atomic.
15. Existing appointments retain their original duration when doctor defaults change.

## Appointment Duration

Each doctor has a configurable appointment duration:

- 15 minutes
- 30 minutes
- 45 minutes
- 60 minutes

Use a 15-minute scheduling grid.

All appointment start times must align with that grid.

A 45-minute appointment reserves three consecutive 15-minute slots.

Store both starts_at and ends_at on the appointment.

## Default Booking Policy

- Maximum advance booking: 30 days
- Minimum lead time: 4 hours
- Minimum receptionist review window: 2 hours
- Pending expiry: earlier of 24 hours after submission or 2 hours before appointment start
- Patient cancellation cutoff: 2 hours before appointment
- No-show grace period: 15 minutes
- Clinic timezone: Asia/Manila

These must be configurable clinic policies where appropriate.

Store appointment timestamps consistently in UTC and convert for display.

## Approval Rules

A receptionist may approve only a pending, unexpired request.

Approval must happen before expires_at.

Approved appointments become confirmed.

Their existing slot reservations remain occupied.

## Rejection Rules

A receptionist can reject a pending request.

Rejection requires a short reason.

Rejecting releases active reservations atomically.

## Expiry Rules

Pending requests expire automatically when the approval deadline passes.

Expiry must:

- Lock and recheck the request
- Set status to expired
- Release doctor and patient reservations
- Create an audit event
- Commit atomically

Approval must enforce expiry independently of the scheduled cleanup process.

## Cancellation Rules

Patients may cancel pending or confirmed appointments before the cancellation cutoff.

Authorized staff may perform exceptional late cancellations with an audit reason.

Cancellation releases active reservations and preserves appointment history.

## Rescheduling

Do not build an in-place rescheduling workflow in MVP.

Patients must cancel an eligible appointment and submit a new request.

Clearly warn that cancellation does not guarantee availability of another slot.

## Attendance Rules

Receptionists can check in confirmed patients.

Receptionists can mark checked-in appointments completed.

Receptionists can mark confirmed appointments as no-show after the configured grace period.

Do not automatically mark appointments as no-show.

---

# 6. APPOINTMENT STATUS MACHINE

Supported statuses:

- pending
- confirmed
- rejected
- expired
- cancelled
- checked_in
- completed
- no_show

Allowed transitions:

pending → confirmed
pending → rejected
pending → expired
pending → cancelled

confirmed → checked_in
confirmed → cancelled
confirmed → no_show

checked_in → completed

All other transitions are prohibited unless explicitly added through a reviewed business requirement.

Implement transitions through a centralized service.

Do not permit arbitrary status updates from form submissions.

---

# 7. DATABASE REQUIREMENTS

Use PostgreSQL migrations, foreign keys, constraints, and indexes.

## Core Tables

### users

- id
- name
- email (unique)
- email_verified_at (legacy framework column; unused by the application)
- password
- role
- is_active
- timestamps

Roles: patient, receptionist, admin.

### patients

- id
- user_id (unique foreign key)
- phone (optional)
- timestamps

Each MVP patient must have a registered user account.

### specialties

- id
- name (unique)
- is_active
- timestamps

### doctors

- id
- specialty_id
- name
- appointment_duration_minutes
- is_active
- timestamps

### doctor_schedules

- id
- doctor_id
- weekday
- start_time
- end_time
- effective_from (optional)
- effective_until (optional)
- timestamps

Schedules must support multiple non-overlapping working intervals per day, including lunch breaks.

Validate interval overlaps and date-effective schedule conflicts.

### doctor_unavailabilities

- id
- doctor_id
- starts_at
- ends_at
- reason (short operational description)
- timestamps

### appointments

- id
- patient_id
- doctor_id
- reference_code (unique)
- starts_at
- ends_at
- duration_minutes
- status
- expires_at
- approved_by (nullable user foreign key)
- approved_at (nullable)
- cancelled_by (nullable user foreign key)
- cancelled_at (nullable)
- idempotency_key
- request_fingerprint
- timestamps

Use a unique constraint on (patient_id, idempotency_key).

Do not store medical diagnoses or detailed health information.

### doctor_slot_reservations

- id
- appointment_id
- doctor_id
- slot_starts_at

Unique constraint:
(doctor_id, slot_starts_at)

### patient_slot_reservations

- id
- appointment_id
- patient_id
- slot_starts_at

Unique constraint:
(patient_id, slot_starts_at)

Reservation rows represent current occupancy.

### appointment_events

- id
- appointment_id
- actor_id (nullable)
- event_type
- old_status (nullable)
- new_status (nullable)
- reason (nullable)
- metadata (restricted JSON)
- created_at

Use append-only audit events.

### clinic_settings

- id
- timezone
- booking_window_days
- minimum_lead_hours
- minimum_review_hours
- approval_expiry_hours
- cancellation_cutoff_hours
- no_show_grace_minutes
- timestamps

Also create a durable operational audit mechanism for doctor schedule changes and administrative configuration changes.

Laravel framework tables for sessions, password resets, jobs, failed jobs, and related infrastructure may be added as needed.

## Database Integrity

- Use foreign keys.
- Use appropriate deletion restrictions.
- Do not cascade-delete appointment history.
- Add indexes for appointment doctor, patient, status, and start time.
- Add unique indexes for occupied slots.
- Add check constraints where useful.
- Prevent inconsistent appointment intervals.
- Ensure all reservations belong to the corresponding appointment and resources.

---

# 8. CONCURRENCY-SAFE BOOKING ENGINE

This is a critical requirement.

A simple SELECT followed by INSERT is not sufficient to prevent simultaneous bookings.

Implement the booking engine using PostgreSQL transactions, row-level locks, and unique reservation constraints.

## Reservation Strategy

For every occupied 15-minute slot, insert:

1. A doctor slot reservation
2. A patient slot reservation

For example, a 45-minute booking starting at 09:00 reserves:

09:00
09:15
09:30

for both the doctor and patient.

## Booking Transaction

1. Validate request format and authentication.
2. Begin database transaction.
3. Lock relevant doctor and patient records in a consistent order.
4. Check whether the idempotency key already exists.
5. Reject reuse of an existing idempotency key with a different request fingerprint.
6. Expire relevant stale pending requests under the same locking protocol.
7. Re-read doctor settings, schedules, breaks, and unavailability.
8. Calculate the requested interval.
9. Validate clinic booking rules.
10. Create the pending appointment.
11. Insert all doctor and patient slot reservations.
12. Create the appointment event.
13. Commit.
14. Dispatch notifications after commit.

Use database unique constraints as the final conflict protection.

Catch PostgreSQL unique violations and return an appropriate conflict response.

Handle deadlocks and serialization failures with bounded retries where safe.

Never send emails inside a database transaction.

## Locking Discipline

All booking, cancellation, rejection, expiry, approval, and schedule-changing operations must use a documented, consistent locking protocol.

For appointment mutations, obtain immutable resource IDs, lock resources in a stable order, then lock and re-read the appointment.

Use doctor lock → patient lock → appointment lock where applicable.

Ensure administrative schedule changes acquire compatible locks.

## Idempotency

Generate a unique submission key per booking attempt.

A retry with the same key and identical payload must not create a second appointment.

A retry with the same key and different payload must be rejected.

---

# 9. DOCTOR SCHEDULING

Administrators must be able to:

- Create doctor profiles
- Assign specialties
- Set consultation duration
- Configure weekly working intervals
- Configure breaks
- Set unavailable dates
- Deactivate doctors

Availability calculation must consider:

- Working hours
- Schedule effective dates
- Break intervals
- Doctor unavailability
- Existing pending reservations
- Existing confirmed reservations
- Clinic booking window
- Minimum booking lead time
- Appointment duration
- 15-minute grid alignment

Schedule changes must not silently invalidate existing bookings.

Before changing working hours, duration, or unavailability, preview affected appointments.

For MVP, reject conflicting ordinary changes until affected bookings are resolved.

Do not automatically move confirmed appointments.

---

# 10. USER INTERFACE REQUIREMENTS

Design KLINIKA-WEB as a modern, professional healthcare scheduling platform.

Design principles:

- Clean, minimal interface
- White and neutral backgrounds
- Teal or blue accent colors
- Consistent typography
- Responsive layouts
- Accessible controls
- Clear visual hierarchy
- Consistent spacing
- Professional forms
- Status badges
- Helpful empty states
- Loading indicators
- Confirmation dialogs
- Clear error messages
- Keyboard accessibility
- Appropriate focus indicators
- Reduced-motion support

Do not prioritize decorative animations over usability.

## Public Pages

- Home
- Doctors
- Doctor details
- Login
- Register
- Forgot password

## Patient Pages

- Dashboard
- Browse doctors
- Book appointment
- My appointments
- Appointment details
- Profile settings

## Receptionist Pages

- Dashboard
- Pending requests
- Appointment calendar
- Appointment details
- Appointment history

## Administrator Pages

- Dashboard
- Doctor management
- Specialty management
- Doctor schedules
- Doctor unavailability
- Receptionist management
- Clinic settings
- Audit history

Use shared Blade layouts and reusable Livewire components.

---

# 11. NOTIFICATIONS

**Product-owner override:** Email notification implementation and delivery are deferred. The notification requirements below remain future scope and must not gate local booking, receptionist workflows, or scheduled expiry until the product owner explicitly restores them.

Use Laravel Notifications.

Initial notifications:

- Appointment request submitted
- Appointment approved
- Appointment rejected
- Appointment expired
- Appointment cancelled

Use queued email delivery when an appropriate worker is available.

Queue notifications after transaction commit.

Do not allow notification failures to roll back successful appointment operations.

For local development, use a log or testing mail transport.

For production, configure a real email provider through environment variables.

Avoid sending sensitive medical information in email messages.

---

# 12. SECURITY REQUIREMENTS

Implement:

- Secure password hashing
- No email-verification gate; active authenticated accounts may continue immediately
- Password reset
- CSRF protection
- Session-based authentication
- Secure production cookies
- Rate limiting
- Server-side input validation
- Authorization policies
- Resource ownership checks
- Staff account provisioning
- Safe error handling
- Secrets stored outside source control
- Minimal personal data collection
- Audit logging
- Safe database queries
- Protection against mass assignment
- Appropriate security headers

Patients must never access another patient's appointment.

Do not trust patient IDs, doctor IDs, roles, or appointment statuses provided by the frontend without server validation.

Use fictional patient data for development and demonstrations.

Before using real patient information, require a separate privacy, legal, and operational readiness review.

---

# 13. TESTING STRATEGY

Write automated tests as features are implemented.

## Authentication Tests

- Patient registration
- Registration without email verification
- Login and logout
- Password reset
- Unauthorized route access
- Staff role restrictions

## Scheduling Tests

- Valid working hours
- Break handling
- Unavailable dates
- Appointment duration
- Booking cutoff
- Schedule conflicts

## Booking Tests

- Successful pending request
- Duplicate booking rejection
- Same-patient overlapping booking rejection
- Same-doctor overlapping booking rejection
- Variable-duration overlap detection
- Idempotent submission
- Expired request handling
- Invalid booking rejection

## Concurrency Tests

Use real PostgreSQL connections and independent concurrent transactions.

Verify:

- Two patients cannot reserve the same doctor slot.
- One patient cannot reserve overlapping doctors.
- Approval and expiry races produce one valid outcome.
- Approval and cancellation races remain consistent.
- Schedule changes cannot race into invalid bookings.
- Unique constraints protect occupancy.

## Workflow Tests

- Receptionist approval
- Receptionist rejection
- Patient cancellation
- Check-in
- Completion
- No-show
- Unauthorized state transitions
- Audit event creation

## Deployment Tests

- Application health endpoint
- Database connectivity
- Migration correctness
- Queue worker operation
- Scheduler operation
- HTTPS behavior
- Restart persistence
- Backup restoration

Do not claim concurrency safety without executing real PostgreSQL concurrency tests.

---

# 14. RENDER DEPLOYMENT REQUIREMENTS

Deploy KLINIKA-WEB as one Docker-based Render web service.

Use managed persistent PostgreSQL.

Create:

- Production Dockerfile
- Nginx configuration
- PHP-FPM configuration
- Container startup configuration
- Health-check endpoint
- .dockerignore
- .env.example
- Deployment documentation

Requirements:

- Run Laravel through PHP-FPM behind Nginx.
- Bind the web server to Render's configured port.
- Set APP_ENV=production.
- Set APP_DEBUG=false.
- Configure APP_KEY securely.
- Configure PostgreSQL credentials through environment variables.
- Configure APP_URL and trusted proxies.
- Use HTTPS and secure session cookies.
- Run migrations through a controlled deployment process.
- Do not rely on ephemeral storage for persistent data.
- Configure a reliable independent scheduler for expiry.
- Configure a reliable queue worker for notifications.
- Implement monitoring and backup procedures.

Render free resources may be useful for demonstrations but must not be treated as suitable for live clinic operations.

Do not assume paid background workers, cron services, or managed backups are available without verifying the selected Render plan.

If the deployment plan cannot support reliable expiry, notifications, and persistence, document the limitation and do not claim the system is production-ready.

---

# 15. DEVELOPMENT PHASES

## PHASE 0 — REPOSITORY AND PLANNING

Goal: Establish project documentation and verify the development environment.

Tasks:

1. Inspect the current workspace.
2. Check PHP, Composer, Node.js, npm, and PostgreSQL availability.
3. Confirm compatible package versions.
4. Create README.md.
5. Create docs/PRD.md.
6. Create docs/ARCHITECTURE.md.
7. Create docs/DATABASE.md.
8. Create docs/BUSINESS_RULES.md.
9. Create docs/PHASES.md.
10. Create AGENTS.md containing project rules for Codex.
11. Document unresolved assumptions.
12. Do not build application features yet.

Acceptance criteria:

- Project structure and documentation are ready.
- Business rules are consistent.
- Technology choices are verified.
- Phase 1 implementation plan is clear.

STOP AND WAIT FOR APPROVAL.

## PHASE 1 — LARAVEL FOUNDATION

Goal: Initialize the application.

Tasks:

1. Install Laravel and compatible Livewire starter kit.
2. Configure PostgreSQL.
3. Configure Tailwind and Vite.
4. Set up authentication.
5. Disable email verification; registration creates an immediately usable patient account.
6. Implement patient, receptionist, and admin roles.
7. Create basic route authorization.
8. Build shared application layouts.
9. Create safe development seeders.
10. Write authentication and authorization tests.

Acceptance criteria:

- Application starts locally.
- PostgreSQL connects.
- Authentication works.
- Role restrictions work.
- Tests pass.

STOP AND WAIT FOR APPROVAL.

## PHASE 2 — DATABASE AND DOMAIN MODELS

Goal: Implement the finalized relational schema.

Tasks:

1. Create migrations.
2. Add foreign keys and indexes.
3. Add booking uniqueness constraints.
4. Create Eloquent models.
5. Define relationships.
6. Create enums.
7. Create factories.
8. Create fictional development seeders.
9. Add database integrity tests.

Acceptance criteria:

- Fresh migrations succeed.
- Models and relationships work.
- Constraints reject invalid data.
- Tests pass.

STOP AND WAIT FOR APPROVAL.

## PHASE 3 — DOCTOR AND SCHEDULE MANAGEMENT

Goal: Build clinic configuration.

Tasks:

1. Specialty management.
2. Doctor management.
3. Consultation duration settings.
4. Weekly working intervals.
5. Break management.
6. Unavailability management.
7. Schedule overlap validation.
8. Schedule-change conflict protection.
9. Admin Livewire interfaces.
10. Feature tests.

Acceptance criteria:

- Admin can configure doctors.
- Invalid schedules are rejected.
- Existing appointments cannot be silently invalidated.

STOP AND WAIT FOR APPROVAL.

## PHASE 4 — AVAILABILITY AND BOOKING ENGINE

Goal: Implement the most important backend logic.

Tasks:

1. AvailabilityService.
2. BookingService.
3. 15-minute slot generation.
4. Variable-duration calculations.
5. Doctor reservation constraints.
6. Patient reservation constraints.
7. PostgreSQL transactions and locking.
8. Idempotency handling.
9. Pending appointment creation.
10. Audit event creation.
11. Conflict error handling.
12. Concurrency tests.

Acceptance criteria:

- Valid booking requests succeed.
- Overlapping bookings fail.
- Concurrent requests cannot double-book.
- Failed transactions leave no partial reservations.
- Tests pass against PostgreSQL.

STOP AND WAIT FOR APPROVAL.

## PHASE 5 — PATIENT PORTAL

Goal: Complete the patient experience.

Tasks:

1. Patient dashboard.
2. Doctor browsing.
3. Availability calendar.
4. Appointment request form.
5. Booking confirmation page.
6. Appointment history.
7. Appointment details.
8. Cancellation workflow.
9. Profile settings.
10. Responsive UI testing.

Acceptance criteria:

- An authenticated active patient can complete the booking journey without email verification.
- Patients cannot access others' records.
- UI handles booking conflicts clearly.

STOP AND WAIT FOR APPROVAL.

## PHASE 6 — RECEPTIONIST WORKFLOWS

Goal: Complete staff appointment management.

Tasks:

1. Receptionist dashboard.
2. Pending request list.
3. Approval workflow.
4. Rejection workflow.
5. Appointment calendar.
6. Check-in workflow.
7. Completion workflow.
8. No-show workflow.
9. Search and filtering.
10. Transition and authorization tests.

Acceptance criteria:

- Staff can manage the appointment lifecycle.
- Invalid transitions are rejected.
- Approval and cancellation races are safe.
- Audit history is recorded.

STOP AND WAIT FOR APPROVAL.

## PHASE 7 — AUTOMATION AND NOTIFICATIONS

Goal: Implement background operations.

Current approved scope is limited to pending expiry, scheduling, failure isolation, and scheduler tests. Notification classes, queued email handling, notification retries, queue tests for notifications, and email failure monitoring remain deferred by product-owner direction.

Tasks:

1. Pending expiry command.
2. Expiry scheduling.
3. Notification classes.
4. Queued email handling.
5. Retry and failure handling.
6. After-commit notification dispatch.
7. Scheduler tests.
8. Queue tests.
9. Monitoring of failed jobs.

Acceptance criteria:

- Expired requests release reservations.
- Expired requests cannot be approved.
- Notification failures do not corrupt bookings.
- Expiry operates independently of web traffic.

STOP AND WAIT FOR APPROVAL.

## PHASE 8 — UI POLISH AND SECURITY HARDENING

Goal: Improve product quality.

Tasks:

1. Responsive UI review.
2. Accessibility improvements.
3. Form validation feedback.
4. Empty and loading states.
5. Confirmation dialogs.
6. Rate limiting.
7. Authorization audit.
8. Session and cookie security.
9. Error handling review.
10. Security-focused feature tests.

Acceptance criteria:

- Critical workflows are accessible and responsive.
- Unauthorized operations fail.
- Sensitive information is protected.
- Tests pass.

STOP AND WAIT FOR APPROVAL.

## PHASE 9 — RENDER DEPLOYMENT

Goal: Deploy a working demonstration.

Tasks:

1. Create Dockerfile.
2. Configure Nginx and PHP-FPM.
3. Configure production environment.
4. Provision persistent PostgreSQL.
5. Create Render web service.
6. Configure HTTPS and health checks.
7. Run migrations safely.
8. Configure queue and scheduler services.
9. Configure logging and monitoring.
10. Validate backups and recovery procedures.
11. Run deployment smoke tests.
12. Document operational limitations.

Acceptance criteria:

- Application is reachable over HTTPS.
- Data survives deployments and restarts.
- Database migrations work.
- Background operations run reliably.
- No development secrets are exposed.

STOP AND WAIT FOR APPROVAL.

## PHASE 10 — FINAL QA AND RELEASE

Goal: Validate the completed MVP.

Tasks:

1. Run full automated test suite.
2. Run PostgreSQL concurrency tests.
3. Test all user roles.
4. Test appointment lifecycle.
5. Test expiry and cancellation.
6. Test schedule modifications.
7. Test production error handling.
8. Review accessibility and responsiveness.
9. Review application logs.
10. Verify backup restoration.
11. Finalize README and deployment documentation.
12. Document known limitations.
13. Prepare fictional demonstration accounts and data.

Acceptance criteria:

- All critical acceptance tests pass.
- No known critical booking or authorization defects remain.
- Deployment is stable.
- Documentation is complete.
- Product is ready for a controlled demonstration or pilot review.

---

# 16. REQUIRED PHASE REPORT FORMAT

At the end of each phase, respond with:

## Phase Completed
Phase number and name.

## Implemented
Describe what was built.

## Files Changed
List important created or modified files.

## Commands Executed
List commands and their results.

## Tests
List tests executed, passing, failing, or not executed.

## Security and Architecture Notes
Explain relevant decisions and remaining risks.

## Manual Verification
Explain what I should test in the browser.

## Git Commit Suggestion
Provide a descriptive commit message.

## Next Phase
Explain what comes next.

Do not begin the next phase automatically.

---

# 17. DEFINITION OF DONE

klinika-web MVP is complete when:

1. Patients can register and use their account without email verification.
2. Patients can browse doctors and available times.
3. Patients can submit appointment requests.
4. Pending requests reserve doctor and patient slots.
5. Receptionists can approve and reject requests.
6. Patients can cancel eligible appointments.
7. Expired requests release their reservations.
8. Receptionists can check in patients and complete visits.
9. Admins can manage doctors and schedules.
10. Doctor duration settings work correctly.
11. Database constraints prevent overlapping bookings.
12. Critical concurrency tests pass.
13. Authorization prevents cross-user access.
14. Application is deployed to Render with persistent PostgreSQL.
15. Scheduler, notifications, monitoring, and backups are verified.
16. Documentation and automated tests are complete.

Production readiness for real patient data requires a separate privacy, security, and operational review.

---

# FINAL INSTRUCTION

Begin with **PHASE 0 ONLY**.

First inspect my current VS Code workspace and development environment.

Create the required planning documents and AGENTS.md.

Identify any technical conflicts, missing prerequisites, or inconsistencies in this specification before implementing application features.

Provide the Phase 0 completion report.

Then STOP and wait for my explicit approval before starting Phase 1.

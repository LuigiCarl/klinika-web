# klinika-web Product Requirements

## Document status

This is a Phase 0 requirements record, not the complete approved master PRD. The requested source file, `projectplan.md`, was inspected on October 22, 2026 and is empty. Only requirements explicitly present in the task are marked confirmed. Proposed scope is isolated so it can be accepted, changed, or removed without being mistaken for approved behavior.

## Confirmed requirements

- Product name: klinika-web.
- Product category: production-oriented clinic appointment management system.
- Intended stack: the latest compatible Laravel release (currently Laravel 13), Livewire, Blade, Tailwind CSS, PostgreSQL, and Render.
- Laravel conventions and security and database-integrity controls are required.
- Architecture should remain simple and maintainable, without unnecessary packages or abstractions.
- Phase 0 is documentation and planning only. Feature implementation, Laravel initialization, and deployment are excluded.

## Proposed product boundary (requires approval)

The smallest coherent first release would allow authorized clinic staff to manage practitioners, patients, services, schedules, and appointments. It would include conflict-safe appointment creation, rescheduling, cancellation, role-based access, and an audit trail for sensitive operational changes.

The following are not approved because the master PRD is unavailable:

- whether this is single-clinic or multi-clinic;
- whether patients can self-register or self-book;
- whether appointments require staff confirmation;
- exact appointment statuses and allowed transitions;
- operating hours, breaks, holidays, buffers, slot intervals, and overbooking policy;
- practitioner-to-service relationships and service-specific durations;
- walk-ins, waitlists, recurring appointments, rooms, equipment, and resource conflicts;
- payments, insurance, billing, refunds, invoices, and deposits;
- medical records, clinical notes, prescriptions, diagnoses, and attachments;
- email/SMS reminders and provider choices;
- timezone, locale, accessibility, privacy jurisdiction, retention, and consent requirements;
- reporting, exports, integrations, and administrative configuration;
- recovery objectives, uptime target, expected traffic, and data volume.

## Proposed actors (requires approval)

| Actor | Proposed responsibility |
| --- | --- |
| Administrator | Manage clinic configuration, users, roles, services, schedules, and reports. |
| Receptionist | Manage patients and the appointment lifecycle, without security administration. |
| Practitioner | View own schedule and permitted patient/appointment details; update appointment outcomes if required. |
| Patient | Self-service booking and own appointments only if a patient portal is approved. |

No role may access another clinic's data if multi-clinic operation is approved. Authorization must be policy-based and enforced server-side.

## Proposed core capabilities (requires approval)

1. Authentication and account recovery.
2. User, role, and clinic membership administration.
3. Patient directory with duplicate-detection rules.
4. Practitioner and service configuration.
5. Weekly availability and dated schedule exceptions.
6. Conflict-safe appointment creation, rescheduling, cancellation, and status history.
7. Calendar and list views with server-side filtering.
8. Audit history for security-sensitive and appointment-changing actions.
9. Notifications and reporting only after channels, templates, consent, and retention are approved.

## Non-functional requirements to approve

- Security baseline and applicable privacy law.
- Availability, latency, backup retention, recovery-point objective, and recovery-time objective.
- Supported browsers, devices, locales, and timezones.
- Accessibility target (WCAG 2.2 AA is proposed).
- Capacity assumptions for clinics, practitioners, patients, appointments, and concurrent booking requests.
- Observability, incident response, and audit retention.

## Acceptance gate

Phase 1 was authorized with unresolved product decisions explicitly deferred. Those decisions remain a gate for the affected Phase 2 and later features.


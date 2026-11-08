# Klinikaly Project Roadmap

This document outlines the current status, short-term priorities, upcoming enhancements, and long-term vision for **Klinikaly**.

Klinikaly is intentionally architected as a **high-reliability single-clinic appointment management platform**. We prioritize bulletproof concurrency, zero double-booking, transparent state machines, and clean operational triage over broad multi-tenant complexity.

---

## 🎯 Current Status (Production-Ready MVP)

The core application is fully functional and covered by 158 automated tests, PHPStan Level 6, and Laravel Pint style checks:

- [x] **Concurrency-Safe Slot Engine**: PostgreSQL row-level locking (`SELECT ... FOR UPDATE`), atomic transactions, and unique slot constraints (`doctor_slot_reservations` and `patient_slot_reservations`).
- [x] **Live Receptionist Desk**: Real-time polling (`wire:poll.10s`), audible audio chime on incoming requests, and one-click triage (Confirm, Reject with audit reason, Check-in, No-show).
- [x] **Conflict-Safe Rescheduling**: In-flight slot swapping with audit event history and patient email dispatch.
- [x] **3-Step Patient Booking Stepper**: Guided flow with quick-jump date chips, Morning/Afternoon slot grouping, and idempotency protection.
- [x] **Admin Analytics & Operations Suite**: KPI dashboard, doctor & schedule roster management, receptionist provisioning, clinic rules configuration, and audit logs.
- [x] **Automated Notifications**: Mailable classes with RFC-5545 `.ics` calendar invite attachments.
- [x] **Production Deployment Ready**: Multi-stage Alpine containerization, Supervisor process supervision, and Render blueprint (`render.yaml`).

---

## 🚀 Near-Term Priorities (v1.1 - Community & Experience)

These are active priorities where contributor involvement is especially welcome:

### 1. Developer Experience & Quickstart
- [ ] Add `docker-compose.yml` for 1-command local PostgreSQL 17 development with Mailpit.
- [ ] Provide optional Sail / devcontainer configuration for contributors on macOS/Linux/WSL.
- [ ] Add pre-commit git hooks (Husky / Pint) example in `CONTRIBUTING.md`.

### 2. Patient Self-Service & Accessibility
- [ ] Patient Appointment History Search & Filter (filter past appointments by doctor or status).
- [ ] Accessible WCAG 2.1 AA keyboard navigation review across Livewire date & time slot pickers.
- [ ] Printable Patient Booking Confirmation voucher (print-friendly CSS view for confirmation page).

### 3. Clinic Operations & Receptionist Tools
- [ ] Enhanced Daily Manifest CSV export filters (filter export by specific doctor or consultation status).
- [ ] Doctor temporary unavailability calendar UI (visual blockouts for conferences, sick leave, emergencies).
- [ ] Configurable audio chime volume and sound selector in Receptionist settings.

---

## 🔮 Future Enhancements (v1.2+)

- [ ] **Multi-Channel Reminders**: SMS notification driver (Twilio / Vonage) for 24-hour appointment reminders.
- [ ] **Queue Display Screen**: Fullscreen, read-only "Now Serving / Waiting Room" public display screen (`/queue/display`) updating in real-time.
- [ ] **Clinic Holidays & Global Blackouts**: Clinic-wide holiday calendar blocking out all doctors on official non-working days.
- [ ] **Webhooks**: Outbound webhooks for appointment status transitions (e.g. notifying external EHR/CRM systems).

---

## 🚫 Out of Scope (Architectural Boundaries)

To keep the codebase maintainable, secure, and focused, the following areas are intentionally **out of scope**:

1. **Multi-Clinic Multi-Tenancy**: Klinikaly is designed for a single clinic facility with multiple doctors and receptionists. We will not add multi-clinic tenancy or tenant isolation layers.
2. **Integrated Payment Gateway**: No credit card capture or merchant gateways; billing and co-pays are handled at the clinic reception desk.
3. **Electronic Health Records (EHR / EMR)**: No clinical diagnostics, prescriptions, or medical histories. Klinikaly strictly manages scheduling, appointments, and operational triage.
4. **Doctor Login Portals**: Doctors do not log in or manage appointments directly; clinic schedules and patient flows are managed by clinic receptionists and administrators.

---

## 🤝 How to Contribute to the Roadmap

Have an idea that fits our vision?
1. Check the [Open Issues](https://github.com/LuigiCarl/klinika-web/issues) or submit a [Feature Proposal](.github/ISSUE_TEMPLATE/feature_request.yml).
2. Look for issues labeled [`good first issue`](https://github.com/LuigiCarl/klinika-web/labels/good%20first%20issue) or [`help wanted`](https://github.com/LuigiCarl/klinika-web/labels/help%20wanted).
3. Read [CONTRIBUTING.md](CONTRIBUTING.md) to get started!


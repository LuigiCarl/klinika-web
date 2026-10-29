# Deployment Guide — Render with Persistent PostgreSQL

This guide describes how to deploy **klinika-web** to [Render](https://render.com) using Docker, Nginx, PHP 8.4-FPM, and Managed PostgreSQL.

---

## 1. Architecture Overview

- **Web Service:** Multi-stage Docker container running PHP 8.4-FPM and Nginx, supervised by `supervisord`. Listens on port `10000` (or dynamic `$PORT`).
- **Database:** Render Managed PostgreSQL (`klinika-postgres`) in the same region (e.g., Singapore) with internal private networking.
- **Queue & Scheduler:** Database-driven queue (`QUEUE_CONNECTION=database`) and scheduler execution via `appointments:expire`.
- **Health Check:** `/up` endpoint monitored by Render for automated restart and zero-downtime deployments.
- **TLS / HTTPS:** Render automatically terminates SSL/TLS and forwards traffic over HTTPS with standard `X-Forwarded-*` headers, trusted by Laravel.

---

## 2. Deployment via Render Blueprint (`render.yaml`)

The repository includes a root `render.yaml` blueprint defining the web service, database, and background worker.

### Steps:
1. Push your repository to GitHub or GitLab.
2. Log in to the [Render Dashboard](https://dashboard.render.com).
3. Click **New +** > **Blueprint**.
4. Connect the `klinika-web` repository.
5. Review the blueprint resources:
   - `klinika-web` (Web Service)
   - `klinika-postgres` (PostgreSQL Database)
   - `klinika-worker` (Background Worker — optional; can be toggled depending on tier)
6. Click **Apply**.
7. Render will automatically provision the PostgreSQL database, build the Docker image, run database migrations, and launch the service.

---

## 3. Environment Variables Reference

| Variable | Recommended Production Value | Description |
| :--- | :--- | :--- |
| `APP_NAME` | `Klinika` | Application brand name |
| `APP_ENV` | `production` | Enables production optimizations and security |
| `APP_DEBUG` | `false` | Disables debug stack traces |
| `APP_KEY` | Auto-generated (`base64:...`) | Encryption key for sessions and credentials |
| `APP_TIMEZONE` | `Asia/Manila` | Clinic operating timezone |
| `DB_CONNECTION` | `pgsql` | Production database driver |
| `DATABASE_URL` | From `klinika-postgres` connection string | Full PostgreSQL connection URL |
| `SESSION_DRIVER` | `database` | Stores sessions durably in PostgreSQL |
| `SESSION_SECURE_COOKIE` | `true` | Enforces HTTPS-only cookies in production |
| `QUEUE_CONNECTION` | `database` | Database-backed queue jobs |
| `CACHE_STORE` | `database` | Database-backed caching store |
| `RUN_MIGRATIONS` | `true` | Runs `php artisan migrate --force` on startup |
| `RUN_SEEDER` | `false` | Run initial clinic seeders (`true` for first deploy only) |

> [!IMPORTANT]
> Keep `SESSION_SECURE_COOKIE=true` in production to protect patient session identifiers from being transmitted over unencrypted connections.

---

## 4. First-Time Setup & Seeding

On the initial deployment, populate the initial clinic roles and baseline settings:

1. In the Render Dashboard, navigate to the `klinika-web` service.
2. Open the **Shell** tab.
3. Run the database seeders:
   ```bash
   php artisan db:seed --force
   ```
4. Verify the clinic settings and administrative accounts are created.

---

## 5. Health Checks and Smoke Testing

### Automated Health Check
Render continuously polls:
```text
GET https://<your-service>.onrender.com/up
```
A `200 OK` response confirms that PHP-FPM and Nginx are operating normally.

### Manual Smoke Test Checklist
1. **Public Catalog:** Navigate to `/doctors` and verify active specialties and doctors are listed.
2. **Patient Registration:** Register a new patient account (`/register`); confirm redirection to patient dashboard without requiring email verification.
3. **Slot Booking:** Select an available doctor slot and submit an appointment request; verify confirmation screen and reference code generation.
4. **Receptionist Flow:** Sign in as receptionist; verify the pending queue displays the request with the amber count badge, and approve using the Flux confirmation modal.
5. **Patient Action:** Verify patient appointment status updates to `confirmed`.

---

## 6. Backups and Disaster Recovery

1. **Automated Backups:** Render Managed PostgreSQL performs daily automated backups with 7-day retention on paid plans.
2. **Manual Snapshot:** Prior to major releases or schema updates:
   ```bash
   # From Render Shell or local machine connected to external connection string:
   pg_dump -d "$DATABASE_URL" -Fc > backup_$(date +%Y%m%d_%H%M%S).dump
   ```
3. **Recovery Procedure:**
   ```bash
   pg_restore --clean --if-exists -d "$DATABASE_URL" backup_<timestamp>.dump
   ```

---

## 7. Operational Limitations & Considerations

- **Free-Tier Inactivity Sleep:** Render free web services spin down after 15 minutes of inactivity. The initial wake-up request can take 30–50 seconds. Upgrading to a Starter instance eliminates sleep delays.
- **Ephemeral Container Storage:** Any file written to the local container filesystem is lost upon redeployment or restart. All durable state (appointments, events, audit logs, sessions) is persisted exclusively in PostgreSQL.
- **PostgreSQL Concurrency:** The booking engine's row-level locks (`FOR UPDATE`) and slot reservations guarantee booking consistency across concurrent requests.


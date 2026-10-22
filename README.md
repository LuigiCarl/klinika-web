# klinika-web

klinika-web is a production-oriented clinic appointment management system planned for Laravel, Livewire, Blade, Tailwind CSS, PostgreSQL, and Render.

## Project status

Phase 1 established the Laravel foundation. Scheduling and clinic-management features have not been implemented.

The current foundation includes:

- Laravel 13 with the official Livewire 4 starter kit;
- Fortify session authentication, registration, password reset, email verification, two-factor authentication, and passkeys;
- fixed administrator, receptionist, practitioner, and patient roles;
- PostgreSQL runtime configuration and Pest coverage for authentication and role authorization.

Product scope remains provisional because `projectplan.md` is empty. See [the architecture](docs/ARCHITECTURE.md), [the provisional data model](docs/DATABASE.md), and [the delivery roadmap](docs/PHASES.md).

## Current stack detected

- PHP 8.4.5
- Laravel 13.38.0
- Livewire 4.4.8
- Tailwind CSS 4.x
- PostgreSQL 18 Windows service; PHP PostgreSQL extensions are enabled
- Node.js 24.21.0 and npm 11.12.1
- Pest 5.3.3

## Local setup

1. Copy `.env.example` to `.env` and generate an application key.
2. Create the PostgreSQL database named by `DB_DATABASE` and set local credentials.
3. Run `composer install`, `npm install`, `php artisan migrate`, and `npm run build`.
4. Start development with `composer run dev`.

Do not commit `.env` or production secrets.

## Documentation

- [Product requirements](docs/PRD.md)
- [Architecture](docs/ARCHITECTURE.md)
- [Database design](docs/DATABASE.md)
- [Business rules](docs/BUSINESS_RULES.md)
- [Implementation phases](docs/PHASES.md)


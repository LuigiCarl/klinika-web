# Contributing to Klinikaly

Thank you for your interest in contributing to **Klinikaly**! As an open-source healthcare appointment and clinic management engine, we welcome contributions ranging from bug fixes and documentation polish to new feature components and performance enhancements.

This document guides you through setting up your local environment, coding standards, automated testing, and our pull request process.

---

## 🧭 Code of Conduct

All contributors and maintainers are expected to abide by our [Code of Conduct](CODE_OF_CONDUCT.md). Please read it to understand our community standards and reporting procedures.

---

## 🛠️ Development Setup

### Prerequisites

Ensure your development machine has the following tools installed:

- **PHP 8.4+** with extensions: `mbstring`, `xml`, `ctype`, `iconv`, `intl`, `pdo_pgsql`, `pdo_sqlite`, `sqlite3`, `pcntl`, `posix`
- **Composer 2.x**
- **Node.js 22.x** and **npm**
- **Git**
- Optional: **PostgreSQL 17** (or Docker) if testing concurrency row-level locking features.

### Step-by-Step Local Setup

1. **Fork and Clone the Repository**:
   ```bash
   git clone https://github.com/<your-username>/klinika-web.git
   cd klinika-web
   ```

2. **Install PHP and Node Dependencies**:
   ```bash
   composer install
   npm install
   ```

3. **Configure Environment**:
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Choose Your Local Database**:

   - **Option A: Quickstart with SQLite (Recommended for UI & Feature Development)**:
     Update your `.env`:
     ```ini
     DB_CONNECTION=sqlite
     DB_DATABASE=database/database.sqlite
     ```
     Create the file and seed the database:
     ```bash
     touch database/database.sqlite
     php artisan migrate --seed
     ```

   - **Option B: PostgreSQL (Recommended for Concurrency & Production Parity)**:
     Ensure PostgreSQL 17 is running locally, create a database named `klinika_web`, and configure `.env`:
     ```ini
     DB_CONNECTION=pgsql
     DB_HOST=127.0.0.1
     DB_PORT=5432
     DB_DATABASE=klinika_web
     DB_USERNAME=postgres
     DB_PASSWORD=your_password
     ```
     Run migrations and seeders:
     ```bash
     php artisan migrate --seed
     ```

5. **Build Frontend Assets and Start Local Servers**:
   ```bash
   # Terminal 1: Vite asset watcher
   npm run dev

   # Terminal 2: Laravel development server
   php artisan serve
   ```
   Visit [http://localhost:8000](http://localhost:8000) in your browser. You can use the **1-Click Demo Access** buttons to log in as an Admin, Receptionist, or Patient.

---

## 🌿 Branch Naming Conventions

Always create a new branch from `main` before starting work. Use descriptive, categorized branch names:

- `feat/short-description` — New features or functional enhancements
- `fix/short-description` — Bug fixes
- `docs/short-description` — Documentation improvements
- `refactor/short-description` — Code improvements without behavior changes
- `test/short-description` — Adding or improving tests
- `chore/short-description` — Maintenance, tooling, or dependency updates

*Example:* `feat/receptionist-filter-by-doctor`, `fix/appointment-cancellation-modal`

---

## 📝 Commit Message Guidelines

We follow [Conventional Commits](https://www.conventionalcommits.org/):

```
<type>(<scope>): <short description in imperative mood>

[optional body explaining motivation and context]

[optional footer(s) like "Closes #123"]
```

### Types:
- `feat`: A new feature
- `fix`: A bug fix
- `docs`: Documentation only changes
- `style`: Changes that do not affect the meaning of the code (formatting, Pint)
- `refactor`: A code change that neither fixes a bug nor adds a feature
- `perf`: A code change that improves performance
- `test`: Adding missing tests or correcting existing tests
- `chore`: Changes to build process or auxiliary tools

*Examples:*
- `feat(patient): add quick-jump date chips in booking wizard`
- `fix(booking): prevent timezone drift on appointment reminder timestamp`
- `docs: add local SQLite quickstart to README`

---

## 📐 Coding Standards & Quality Gates

Before opening a pull request, verify that your changes pass all local quality checks:

### 1. Code Style (Laravel Pint)
We enforce Laravel code style conventions using Laravel Pint:
```bash
# Auto-fix code formatting
composer lint

# Verify code style compliance (runs in CI)
composer lint:check
```

### 2. Static Analysis (PHPStan / Larastan)
We require Level 6 PHPStan static analysis with 0 errors:
```bash
composer types:check
```

### 3. Automated Tests (Pest PHP)
All tests must pass before submitting your PR:
```bash
# Run test suite
php artisan test --compact

# Or run tests directly with Pest
vendor/bin/pest

# Run a specific test file
vendor/bin/pest tests/Feature/DoctorSearchAndStepperTest.php
```

> [!NOTE]
> By default, `phpunit.xml` runs tests in-memory on SQLite. 11 PostgreSQL-specific concurrency tests will be skipped automatically on SQLite and will run against PostgreSQL 17 in GitHub Actions CI.

---

## 🚀 Pull Request Process

1. **Check for Existing Issues**: Search the [Issue Tracker](https://github.com/LuigiCarl/klinika-web/issues) or open a new issue to discuss major proposed changes before writing substantial code.
2. **Sync with Main**: Rebase your branch onto the latest `main`:
   ```bash
   git fetch origin
   git rebase origin/main
   ```
3. **Verify Locally**: Ensure `composer lint:check`, `composer types:check`, and `php artisan test` all pass.
4. **Push and Open PR**: Push your branch to your fork and submit a PR to `LuigiCarl/klinika-web:main`.
5. **Fill out the PR Template**: Detail the problem solved, changes made, and test commands executed. Attach before/after screenshots for any UI changes.
6. **Code Review**: A maintainer will review your pull request, provide feedback, and merge once CI passes.

---

## 🐛 Reporting Bugs

Found a bug? We appreciate your help in making Klinikaly more robust!

1. Check existing issues to verify it hasn't already been reported.
2. Open a new issue using our **[Bug Report Template](.github/ISSUE_TEMPLATE/bug_report.yml)**.
3. Include:
   - Clear description and steps to reproduce.
   - Expected vs. actual behavior.
   - Environment details (PHP version, database type, browser).
   - Error stack traces or relevant screenshots.

---

## 💡 Proposing Features

Have an idea to improve clinic workflows or patient experience?

1. Review [ROADMAP.md](ROADMAP.md) and existing issues.
2. Submit a proposal using our **[Feature Request Template](.github/ISSUE_TEMPLATE/feature_request.yml)**.
3. Detail the clinic or patient use case, problem solved, and any design ideas.


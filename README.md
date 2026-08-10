# Mastery Learning Center

Mastery Learning Center is a Laravel and Vue application for competency-based learning. It supports academic content authoring, class delivery, assessment attempts and grading, mastery evaluation, remedial learning, parent visibility, and progress reporting.

## Technology

- PHP 8.4.1 or newer in the PHP 8.4 series
- Laravel 13, Inertia 3, Vue 3, TypeScript, and Tailwind CSS
- MySQL 8.4 in production; SQLite is used for the fast CI quality suite
- Node.js 22 and Composer 2 for builds

## Local setup

```bash
cp .env.example .env
composer install
php artisan key:generate
npm install
php artisan migrate --seed
npm run build
php artisan serve
```

Configure the MySQL connection in `.env` before migrating. The local database seeder creates demonstration users and learning data; it deliberately skips all demo data when `APP_ENV=production`.

For concurrent local development, run the web server, queue worker, and frontend watcher in separate terminals:

```bash
php artisan serve
php artisan queue:work --tries=3
npm run dev
```

## Quality checks

Run the same full quality gate used by CI:

```bash
composer ci:check
```

This runs ESLint, Prettier validation, Vue/TypeScript checking, Pint, Larastan, and the Laravel test suite. GitHub Actions also runs the PHP suite against MySQL 8.4 to catch engine-specific behavior.

Dependency checks:

```bash
composer audit
npm audit
```

## Production

Do not deploy with development defaults. Use `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, secure cookies, a durable MySQL database, a supervised queue worker, and a cron-driven scheduler. Point the web server document root at `public/`, never the repository root.

See [Deployment](docs/DEPLOYMENT.md), [Operations](docs/OPERATIONS.md), and [Security and access matrix](docs/SECURITY.md). Candidate post-V1 ideas are documented in [Roadmap](docs/ROADMAP.md).

The application health endpoint is `GET /up`.

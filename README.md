# Mastery Learning Center

Mastery Learning Center is a Laravel and Vue application for competency-based learning. It supports rich multimedia Lesson authoring, class delivery, assessment attempts and grading, mastery evaluation, remedial learning, parent visibility, and progress reporting.

## Technology

- PHP 8.4.1 or newer in the PHP 8.4 series
- Laravel 13, Inertia 3, Vue 3, TypeScript, and Tailwind CSS
- MySQL 8.4 in production; SQLite is used for the fast CI quality suite
- Node.js 22 and Composer 2 for builds

## Local setup with Docker

Docker Compose is configured through Laravel Sail and includes PHP 8.4 and MySQL 8.4. After cloning the project, prepare the application and start the containers:

```bash
cp .env.example .env
composer install
php artisan key:generate
./vendor/bin/sail up -d
./vendor/bin/sail npm install
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm run build
```

The application is available at `http://localhost:8000`. MySQL is exposed at `127.0.0.1:3307` to avoid conflicts with a native MySQL installation, while Laravel reaches it through the Compose service name `mysql` on port `3306`. Database data is retained in the `sail-mysql` Docker volume.

If the host does not have the required PHP 8.4 and Composer versions, install the PHP dependencies with a one-off Composer container before starting Sail:

```bash
cp .env.example .env
docker run --rm -u "$(id -u):$(id -g)" -v "$PWD:/app" composer:2 composer install --ignore-platform-reqs
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

The local database seeder creates demonstration users and learning data; it deliberately skips all demo data when `APP_ENV=production`.

For concurrent local development, run the web server, queue worker, and frontend watcher in separate terminals:

```bash
./vendor/bin/sail up -d
./vendor/bin/sail artisan queue:work --tries=3
./vendor/bin/sail npm run dev
```

Stop the containers with `./vendor/bin/sail down`. To also delete the local MySQL data, use `./vendor/bin/sail down -v`.

For a native setup without Docker, use PHP 8.4, set `DB_HOST=127.0.0.1` and suitable MySQL credentials in `.env`, then run the equivalent `php artisan` and `npm` commands directly.

## Quality checks

Run the same full quality gate used by CI:

```bash
./vendor/bin/sail composer ci:check
```

This runs ESLint, Prettier validation, Vue/TypeScript checking, Pint, Larastan, and the Laravel test suite. GitHub Actions also runs the PHP suite against MySQL 8.4 to catch engine-specific behavior.

Dependency checks:

```bash
./vendor/bin/sail composer audit
./vendor/bin/sail npm audit
```

## Production

Do not deploy with development defaults. Use `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, secure cookies, a durable MySQL database, a supervised queue worker, and a cron-driven scheduler. Point the web server document root at `public/`, never the repository root.

See [Deployment](docs/DEPLOYMENT.md), [Operations](docs/OPERATIONS.md), and [Security and access matrix](docs/SECURITY.md). Candidate post-V1 ideas are documented in [Roadmap](docs/ROADMAP.md).

The structured Tiptap Lesson format, private asset policy, and legacy conversion strategy are documented in [Rich lesson content](docs/LESSON_CONTENT.md).

The application health endpoint is `GET /up`.

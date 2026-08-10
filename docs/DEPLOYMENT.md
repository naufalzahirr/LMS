# Production deployment

## Runtime requirements

- PHP 8.4 with PDO MySQL, mbstring, OpenSSL, tokenizer, XML, ctype, JSON, BCMath, and fileinfo
- MySQL 8.4
- Composer 2 and Node.js 22 for the release build
- A process supervisor for queue workers and cron access for the scheduler
- HTTPS termination and a web root set to the application's `public/` directory

## Environment baseline

Create `.env` from `.env.example`, generate a unique `APP_KEY`, and supply secrets through the deployment platform. At minimum, review:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://learning.example.com
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=database.internal
DB_PORT=3306
DB_DATABASE=mastery_learning_center
DB_USERNAME=mastery_app
DB_PASSWORD=use-a-secret-manager

SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
CACHE_STORE=database
QUEUE_CONNECTION=database

MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=learning@example.com
```

Use a least-privileged database account. Restrict writable filesystem access to `storage/` and `bootstrap/cache/`. Uploaded lesson files remain on the private local disk and must not be exposed by a public storage symlink or generic storage-serving route. For multi-node deployments, replace the local private disk with a private shared/object store and preserve authorization at the download controller.

## Release procedure

Build a release artifact in CI or an isolated build environment:

```bash
composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader
npm ci
npm run build
```

On the release host:

```bash
php artisan down --retry=60
php artisan migrate --force
php artisan db:seed --class=RolePermissionSeeder --force
php artisan optimize
php artisan queue:restart
php artisan up
```

Do not run the general `DatabaseSeeder` as a way to create an administrator. Demo seeders are disabled in production, and production administrator credentials must be provisioned through a controlled one-time process.

Run the scheduler every minute:

```cron
* * * * * cd /var/www/mastery-learning-center && php artisan schedule:run >> /dev/null 2>&1
```

Supervise at least one long-lived worker:

```bash
php artisan queue:work --sleep=3 --tries=3 --max-time=3600
```

## Verification and rollback

After release, verify `/up`, authentication, a private lesson download with an authorized account, a student assessment attempt, and the queue. Monitor application, web-server, database, and worker logs.

Before migration, take a tested database backup and retain the previous release artifact. If verification fails, enable maintenance mode, restore the previous artifact, run only a reviewed reversible migration rollback when safe, restore the database if required, rebuild caches, restart workers, and then leave maintenance mode. Data migrations are not assumed reversible without a backup.

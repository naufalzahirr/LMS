# Operations runbook

## Routine checks

- Probe `GET /up` from outside the application network.
- Alert on HTTP 5xx rates, queue failures, worker absence, database saturation, disk capacity, and backup failures.
- Review `storage/logs/laravel.log` through centralized logging; do not expose logs through the web server.
- Run `php artisan queue:failed` and investigate failures before retrying them.
- Run `composer audit` and `npm audit` on every dependency update and on a scheduled basis.

## Backups

Back up the MySQL database and the private lesson-file store. Encrypt backups, restrict access, retain multiple restore points, and perform regular restore drills. A database-only backup is incomplete because lesson metadata and private file contents must remain consistent.

## Cache and worker lifecycle

Use `php artisan optimize` after each release. Use `php artisan optimize:clear` while diagnosing stale configuration, route, or view state, then rebuild caches. Restart workers with `php artisan queue:restart` after code changes.

## Incident response

For suspected unauthorized access, preserve logs, rotate affected credentials, invalidate sessions, restrict service access, and identify exposed students/classes/files before restoring normal traffic. Keep `APP_DEBUG=false`; production error pages provide a safe message while full exceptions remain in protected logs.

For a stuck assessment or mastery calculation, preserve the affected attempt and enrollment rows before changing data. Concurrency-sensitive services lock enrollment, attempt, assignment, and remedial records; manual database edits can bypass those invariants and require a reviewed repair script.

## Data growth

Progress lists and assessment review screens are paginated, parent assessment history is capped to the ten most recent attempts per enrollment, and reporting queries aggregate attempt totals in SQL. Review slow-query logs as data grows and validate new indexes with production-like data before adding them.

# SAMS Production Readiness

Status: MVP release gate completed locally on 11 July 2026.

## Required Runtime

- PHP 8.3 or newer with production extensions
- MySQL 8 or PostgreSQL 16+
- Node.js 20.19+ or 22.12+
- Nginx, PHP-FPM, Redis, Supervisor/systemd, and TLS

## Pre-deployment

1. Create an off-server database backup and verify that it can be restored.
2. Store `.env` only on the server; use `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`, strong `APP_KEY`, and non-default database credentials.
3. Configure production mail, Redis cache/session/queue, timezone, log rotation, and object storage or persistent attachment storage.
4. Run `composer audit --locked` and `npm audit`.
5. Run the complete test suite and production asset build.

## Deployment

```bash
composer install --no-dev --optimize-autoloader
npm ci
npm run build
php artisan migrate --force
php artisan storage:link
php artisan optimize
php artisan queue:restart
```

Configure the scheduler:

```cron
* * * * * cd /var/www/sams && php artisan schedule:run >> /dev/null 2>&1
```

Run the queue worker under Supervisor or systemd with automatic restart.

## Release Verification

- `GET /up` returns HTTP 200.
- Login works for super admin, purchasing, warehouse, finance, and staff.
- Each role sees only entitled modules and actions.
- PR → PO → GR updates budget and stock once.
- Stock Opname posts one adjustment per line.
- Reversal creates contra movements and never deletes ledger history.
- Locked periods reject procurement and inventory posting.
- Attachments upload, download, and delete on persistent storage.
- Print views and CSV exports open correctly.
- Mobile sidebar works at 390 px and tablet layout at 768 px.

## Backup and Monitoring

- Nightly encrypted database backup with retention of 7 daily, 4 weekly, and 12 monthly copies.
- Daily attachment backup and regular restore drills.
- Monitor `/up`, queue failures, disk usage, database connections, HTTP 5xx rate, and backup age.
- Alert on failed jobs, repeated login failures, and application errors.

## Rollback

1. Enable maintenance mode: `php artisan down --retry=60`.
2. Restore the previous application release.
3. Roll back database migrations only when the migration is explicitly reversible and no new production data depends on it; otherwise restore the verified pre-deployment backup.
4. Run `php artisan optimize:clear && php artisan optimize`.
5. Restart PHP-FPM and queue workers, verify `/up`, then run `php artisan up`.

## Go-live Sign-off

Record the release commit, database backup identifier, migration batch, tester, approver, deployment time, and rollback owner for every production release.

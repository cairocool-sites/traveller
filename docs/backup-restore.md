# Backup and Restore Strategy

Phase 10 documents backup readiness only. No real backups are created by this repository.

## What To Back Up

- MySQL 8 database.
- `.env` and server configuration, stored securely outside the repository.
- Private payment evidence.
- Generated voucher, invoice, and receipt snapshots.
- Uploaded hotel images.
- Future supplier reconciliation files, if any.

Exclude `vendor/`, `node_modules/`, cache directories, compiled views, transient logs when centrally managed, and `public/build/` if it can be rebuilt.

## Database Backup

Example command shape:

```bash
mysqldump --single-transaction --routines --triggers --set-gtid-purged=OFF database_name > traveller-YYYYMMDD-HHMM.sql
```

Do not store database dumps in Git.

## File Backup

Back up `storage/app/private`, `storage/app/public`, and required generated document paths. Encrypt archives before off-server transfer.

## Retention

Initial placeholder schedule:

- Hourly database backups for the last 24 hours.
- Daily backups for 30 days.
- Monthly backups for 12 months.

Final retention must be confirmed with legal, accounting, and Egyptian tax requirements.

## Restore Procedure

1. Provision a clean server.
2. Install PHP, Nginx, MySQL, Redis, Composer, and Node.
3. Restore source from a tagged release.
4. Restore `.env` from secure storage.
5. Restore MySQL backup into an empty database.
6. Restore private and public storage.
7. Run `composer install --no-dev --prefer-dist --optimize-autoloader`.
8. Run `npm ci && npm run build`.
9. Run `php artisan migrate --force`.
10. Run `php artisan app:check-environment`.
11. Check `/health/live`, `/health/ready`, admin login, public hotel search, booking status, payment evidence access, and document verification.

## RPO and RTO

RPO and RTO are placeholders until the business confirms acceptable data loss and downtime.

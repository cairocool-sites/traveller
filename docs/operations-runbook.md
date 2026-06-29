# Operations Runbook

Phase 10 adds operational foundations only. It does not deploy, connect real suppliers, add online payment, or start Phase 11 work.

## Health Checks

- `/health/live`: minimal liveness response.
- `/health/ready`: readiness response with no technical details, secrets, paths, table names, or versions.
- `/up`: Laravel framework health endpoint.
- Admin System Health page: protected by `view_system_health`.

## Environment Check

Run:

```bash
php artisan app:check-environment
```

The command checks APP_KEY, production debug mode, HTTPS URL, database, Redis configuration when selected, queue configuration, mail configuration, storage writability, required PHP extensions, scheduler expectations, and obvious placeholder values. It never prints passwords or tokens.

## Scheduler

Cron must run every minute:

```cron
* * * * * php /path/to/artisan schedule:run
```

The scheduled heartbeat command is:

```bash
php artisan ops:scheduler-heartbeat
```

Readiness and the admin page treat the scheduler as stale after `TRAVEL_SCHEDULER_STALE_AFTER_MINUTES`.

## Queues

Production should use Redis-backed queues. Suggested queue groups:

- `default`: normal application work.
- `supplier`: future supplier work.
- `documents`: future document generation work.
- `financial`: payment, receipt, and refund review support work.

Safe retry candidates: search, hotel details, health checks, selected booking lookups, and document regeneration when idempotent. Do not automatically retry booking creation, cancellation submission, or refund completion without reconciliation.

Useful commands:

```bash
php artisan queue:failed
php artisan queue:retry <id>
php artisan queue:restart
```

## Cleanup

Dry run:

```bash
php artisan ops:cleanup --dry-run
```

Actual cleanup:

```bash
php artisan ops:cleanup
```

Cleanup only targets expired transient data and old sanitized supplier logs. It must not delete confirmed bookings, issued invoices, receipts, vouchers, cancellations, refunds, or audit histories.

## Admin System Health

The admin page shows safe summaries only: readiness, scheduler heartbeat, failed jobs, pending manual-review bookings, pending payment reviews, pending cancellation reviews, pending refund reviews, recent supplier failures, mail configuration, and backup metadata placeholder. It does not expose raw logs, file paths, credentials, or customer PII.

## Incident Notes

Use the `X-Correlation-ID` response header when discussing incidents. Public error pages may show a reference code but not stack traces.

## Deferred Decisions

- Monitoring provider.
- Backup storage provider.
- Final RPO/RTO targets.
- Supervisor worker counts for production traffic.
- Legal retention periods.

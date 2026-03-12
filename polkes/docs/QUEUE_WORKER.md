# BHP reminder – sync flow (no queue)

This project sends BHP expiration reminders **synchronously**. No queue workers are used.

## Required setup

- **`.env`**: `QUEUE_CONNECTION=sync`
- **Cron**: only schedule runner is needed:

  ```cron
  * * * * * cd /path/to/polkes && php artisan schedule:run >> /dev/null 2>&1
  ```

- **Scheduler** runs `bhp:check-expiration` once per day at 08:00 (see `app/Console/Kernel.php`).

## Flow

1. Cron runs `schedule:run` every minute.
2. Scheduler runs `bhp:check-expiration` once per day at 08:00.
3. Command checks BHP items expiring within the threshold (default 30 days).
4. Emails are sent **immediately** via `Mail::send()` (no background jobs).
5. Duplicate runs are prevented by the scheduler’s `withoutOverlapping()` (no cache/Redis lock; safe with `CACHE_DRIVER=file`).

No `queue:work` or Redis is required. Safe for shared hosting (e.g. Hostinger).

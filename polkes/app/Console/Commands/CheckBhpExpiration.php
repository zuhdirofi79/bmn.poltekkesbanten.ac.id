<?php

namespace App\Console\Commands;

use App\BarangBHP_bahan;
use App\Mail\BhpExpirationReminder;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Send daily email reminder for BHP items expiring within a defined threshold.
 *
 * Architecture: sync only. No queue workers. Cron runs schedule:run;
 * this command runs once per day (scheduler). Emails are sent immediately.
 * QUEUE_CONNECTION must be "sync" on shared hosting.
 */
class CheckBhpExpiration extends Command
{
    /** Days before expiration to include in reminder (e.g. 30 = within 30 days). */
    const EXPIRATION_THRESHOLD_DAYS = 30;

    protected $signature = 'bhp:check-expiration';

    protected $description = 'Send daily BHP expiration reminder (sync; runs once per day via scheduler)';

    public function handle()
    {
        if (config('queue.default') !== 'sync') {
            Log::warning('BHP reminder: QUEUE_CONNECTION is not sync. Emails may not be sent as expected.');
        }

        $timezone = config('app.timezone', 'UTC');
        $today = Carbon::today($timezone);
        $endDate = Carbon::today($timezone)->addDays(self::EXPIRATION_THRESHOLD_DAYS);

        Log::info('BHP reminder batch started', [
            'timestamp'       => now()->toIso8601String(),
            'timezone'        => $timezone,
            'threshold_days'  => self::EXPIRATION_THRESHOLD_DAYS,
        ]);

        $items = $this->getItemsNearingExpiration($today, $endDate);

        if ($items->isEmpty()) {
            Log::info('BHP reminder skipped: no items nearing expiration', [
                'count' => 0,
            ]);
            $this->info('No BHP items expiring within ' . self::EXPIRATION_THRESHOLD_DAYS . ' days.');
            return 0;
        }

        Log::info('BHP reminder items detected', [
            'count' => $items->count(),
        ]);

        $recipients = $this->getRecipients();
        if (empty($recipients)) {
            Log::warning('BHP reminder skipped: no recipients configured');
            $this->warn('No recipients configured.');
            return 0;
        }

        Log::info('BHP reminder recipients', [
            'recipients' => $recipients,
        ]);

        $emailData = $this->buildEmailData($items, $today);
        $sentCount = 0;
        $failCount = 0;

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new BhpExpirationReminder($emailData));
                $sentCount++;
                Log::info('BHP reminder sent', [
                    'recipient' => $email,
                ]);
                $this->info("Reminder sent to: {$email}");
            } catch (\Throwable $e) {
                $failCount++;
                Log::error('BHP reminder send failed', [
                    'recipient' => $email,
                    'error'     => $e->getMessage(),
                ]);
                $this->error("Failed to send to {$email}: " . $e->getMessage());
            }
        }

        Log::info('BHP reminder batch completed', [
            'sent'   => $sentCount,
            'failed' => $failCount,
        ]);

        return 0;
    }

    /**
     * Items with tgl_expired between today and today + threshold (inclusive).
     * Timezone-safe; single query; no N+1.
     */
    protected function getItemsNearingExpiration(Carbon $today, Carbon $endDate)
    {
        $todayStr = $today->format('Y-m-d');
        $endStr = $endDate->format('Y-m-d');

        return BarangBHP_bahan::whereNull('delete')
            ->whereNotNull('tgl_expired')
            ->whereRaw('tgl_expired >= ? AND tgl_expired <= ?', [$todayStr, $endStr])
            ->orderBy('tgl_expired')
            ->get();
    }

    /**
     * Build array for email template: nama, merk, jumlah, jurusan, tgl_expired, sisa_hari.
     */
    protected function buildEmailData($items, Carbon $today): array
    {
        return $items->map(function ($item) use ($today) {
            $expiredDate = $item->tgl_expired
                ? Carbon::parse($item->tgl_expired, config('app.timezone'))
                : null;
            $sisaHari = 0;
            if ($expiredDate) {
                $sisaHari = (int) $today->diffInDays($expiredDate, false);
                if ($sisaHari < 0) {
                    $sisaHari = 0;
                }
            }

            $jumlah = $item->jumlah ?? $item->stok ?? 0;
            $jumlah = is_numeric($jumlah) ? (int) $jumlah : 0;

            return [
                'nama'        => $item->nama ?? '-',
                'merk'        => $item->merk ?? $item->kimia ?? '-',
                'jumlah'      => $jumlah,
                'jurusan'     => $item->jurusan ?? '-',
                'tgl_expired' => $item->tgl_expired ?? '-',
                'sisa_hari'   => $sisaHari,
            ];
        })->values()->all();
    }

    /**
     * Get list of email addresses to receive the reminder.
     */
    protected function getRecipients(): array
    {
        // Phase 1: single recipient
        return [
            'septiana.anggraini13@gmail.com',
        'auloraqowiyboyqowboy@gmail.com',
    ];

        // Phase 2: all PLP users (uncomment when ready)
        // return User::where('role', 'plp')->pluck('email')->filter()->unique()->values()->all();
    }
}

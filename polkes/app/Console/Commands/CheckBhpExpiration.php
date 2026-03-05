<?php

namespace App\Console\Commands;

use App\BarangBHP_bahan;
use App\Mail\BhpExpirationReminder;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckBhpExpiration extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bhp:check-expiration';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send daily email reminder for BHP items expiring within 7 days';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $today = Carbon::today();
        $endDate = Carbon::today()->addDays(7);

        $items = BarangBHP_bahan::whereNull('delete')
            ->whereNotNull('tgl_expired')
            ->whereRaw('tgl_expired >= ?', [$today->format('Y-m-d')])
            ->whereRaw('tgl_expired <= ?', [$endDate->format('Y-m-d')])
            ->orderBy('tgl_expired')
            ->get();

        if ($items->isEmpty()) {
            $this->info('No BHP items expiring in the next 7 days.');
            return 0;
        }

        $emailData = $items->map(function ($item) use ($today) {
            $expiredDate = $item->tgl_expired
                ? Carbon::parse($item->tgl_expired)
                : null;
            $sisaHari = $expiredDate ? (int) $today->diffInDays($expiredDate, false) : 0;
            if ($sisaHari < 0) {
                $sisaHari = 0;
            }

            return [
                'nama'       => $item->nama ?? '-',
                'jurusan'    => $item->jurusan ?? '-',
                'tgl_expired' => $item->tgl_expired ?? '-',
                'sisa_hari'  => $sisaHari,
            ];
        })->toArray();

        $recipients = $this->getRecipients();

        foreach ($recipients as $email) {
            try {
                Mail::to($email)->send(new BhpExpirationReminder($emailData));
                $this->info("Reminder sent to: {$email}");
            } catch (\Exception $e) {
                $this->error("Failed to send to {$email}: " . $e->getMessage());
            }
        }

        return 0;
    }

    /**
     * Get list of email addresses to receive the reminder.
     * Phase 1: single hardcoded address.
     * Phase 2: all users with role = 'plp' (uncomment the query below).
     *
     * @return array
     */
    protected function getRecipients(): array
    {
        // Phase 1: single recipient
        return ['septiana.anggraini13@gmail.com'];

        // Phase 2: all PLP users (uncomment when ready)
        // return User::where('role', 'plp')->pluck('email')->filter()->unique()->values()->all();
    }
}

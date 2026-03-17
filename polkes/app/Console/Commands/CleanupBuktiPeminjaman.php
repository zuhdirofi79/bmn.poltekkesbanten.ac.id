<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Garbage collector: hapus file bukti foto di storage yang tidak punya record di peminjaman_foto.
 * Dijalankan mingguan via Laravel Scheduler.
 */
class CleanupBuktiPeminjaman extends Command
{
    protected $signature = 'cleanup:bukti-peminjaman';

    protected $description = 'Hapus file orphan di storage/app/public/bukti_peminjaman yang tidak ada di tabel peminjaman_foto';

    public function handle()
    {
        $files = Storage::disk('public')->files('bukti_peminjaman');
        $deleted = 0;

        foreach ($files as $file) {
            $existsInDb = DB::table('peminjaman_foto')
                ->where('foto_path', $file)
                ->exists();

            if (!$existsInDb) {
                Storage::disk('public')->delete($file);
                Log::info('Deleted orphan foto: ' . $file);
                $deleted++;
            }
        }

        if ($deleted > 0) {
            $this->info("Cleanup selesai. {$deleted} file orphan dihapus.");
        } else {
            $this->info('Cleanup selesai. Tidak ada file orphan.');
        }

        return 0;
    }
}

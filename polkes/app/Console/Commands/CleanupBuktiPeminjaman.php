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
        $deleted = 0;

        // Ambil semua foto yang peminjamannya sudah selesai / dikembalikan
        $rows = DB::table('peminjaman_foto as pf')
            ->join('peminjaman as p', 'pf.peminjaman_id', '=', 'p.id')
            ->whereIn('p.status', ['selesai', 'dikembalikan']) // SESUAIKAN dengan value kamu
            ->get(['pf.id', 'pf.foto_path']);

        foreach ($rows as $row) {
            // Hilangkan 'storage/' agar cocok dengan disk public
            $relativePath = str_replace('storage/', '', $row->foto_path);

            if (Storage::disk('public')->exists($relativePath)) {
                Storage::disk('public')->delete($relativePath);

                // Hapus juga dari DB
                DB::table('peminjaman_foto')->where('id', $row->id)->delete();

                Log::info('Deleted foto (selesai): ' . $relativePath);
                $deleted++;
            }
        }

        $this->info("Cleanup selesai. {$deleted} file dihapus berdasarkan status.");

        return 0;
    }
}

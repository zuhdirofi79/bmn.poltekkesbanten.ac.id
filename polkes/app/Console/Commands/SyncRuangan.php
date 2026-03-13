<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time data migration: match barang_inventaris.ruang (free text)
 * against ruangan.nama_ruang and set ruangan_id on matching rows.
 *
 * Safe to run multiple times (idempotent).
 * Only updates rows where ruangan_id IS NULL and a match is found.
 *
 * Usage:
 *   php artisan inventory:sync-room
 *   php artisan inventory:sync-room --dry-run
 */
class SyncRuangan extends Command
{
    protected $signature = 'inventory:sync-room
                            {--dry-run : Preview matches without writing to DB}';

    protected $description = 'Populate barang_inventaris.ruangan_id by matching ruang text to ruangan.nama_ruang';

    public function handle()
    {
        $dryRun = $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY RUN] No changes will be written.');
        }

        // Find rows that can be auto-matched
        $matches = DB::select("
            SELECT b.id, b.ruang, r.id AS ruangan_id, r.kampus, r.nama_ruang
            FROM barang_inventaris b
            JOIN ruangan r ON TRIM(b.ruang) = TRIM(r.nama_ruang)
            WHERE b.ruangan_id IS NULL
              AND b.delete IS NULL
        ");

        if (empty($matches)) {
            $this->info('No unmatched rows found. Nothing to do.');
            return 0;
        }

        $this->table(
            ['ID', 'ruang (text)', 'ruangan_id', 'kampus', 'nama_ruang'],
            array_map(fn($r) => [
                $r->id,
                $r->ruang,
                $r->ruangan_id,
                $r->kampus,
                $r->nama_ruang,
            ], $matches)
        );

        $this->info(count($matches) . ' row(s) will be updated.');

        if ($dryRun) {
            $this->warn('Re-run without --dry-run to apply changes.');
            return 0;
        }

        if (!$this->confirm('Apply these updates?', true)) {
            $this->info('Aborted.');
            return 0;
        }

        $affected = DB::statement("
            UPDATE barang_inventaris b
            JOIN ruangan r ON TRIM(b.ruang) = TRIM(r.nama_ruang)
            SET b.ruangan_id = r.id
            WHERE b.ruangan_id IS NULL
              AND b.delete IS NULL
        ");

        $this->info('Done. Rows updated: ' . count($matches));

        // Report any remaining unmatched rows
        $unmatched = DB::select("
            SELECT id, ruang
            FROM barang_inventaris
            WHERE ruangan_id IS NULL
              AND ruang IS NOT NULL
              AND ruang != ''
              AND delete IS NULL
        ");

        if (!empty($unmatched)) {
            $this->warn(count($unmatched) . ' row(s) could not be auto-matched (text differs from ruangan.nama_ruang):');
            $this->table(['ID', 'ruang (unmatched)'],
                array_map(fn($r) => [$r->id, $r->ruang], $unmatched)
            );
            $this->warn('Update these manually via the inventory edit form.');
        }

        return 0;
    }
}

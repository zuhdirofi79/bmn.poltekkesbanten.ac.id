<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Support\Persediaan\UnitKerja;
use Illuminate\Support\Facades\DB;

/**
 * Generator nomor permintaan: PRM-{KODE_UNIT}-{TAHUN}-{URUTAN}.
 * Contoh: PRM-KEP-2026-001.
 *
 * Urutan diambil dari nomor terakhir per (unit_kerja, tahun berjalan).
 * Tidak reset kecuali tahun berubah. Anti-duplikat memakai lock transaksi.
 *
 * Catatan: panggil di dalam DB::transaction() pemanggil (controller) agar
 * lockForUpdate efektif sampai baris baru ter-insert.
 */
class NomorPermintaanService
{
    public function generate(string $unitKerja, ?int $year = null): string
    {
        $year = $year ?? (int) date('Y');
        $kode = UnitKerja::kode($unitKerja);
        $prefix = sprintf('PRM-%s-%d-', $kode, $year);

        // Ambil nomor terakhir untuk (unit, tahun) dengan lock baris.
        $last = DB::table('persediaan_permintaan')
            ->where('unit_kerja', $unitKerja)
            ->where('nomor_permintaan', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderBy('nomor_permintaan', 'desc')
            ->value('nomor_permintaan');

        $next = 1;
        if ($last !== null) {
            $suffix = substr((string) $last, strlen($prefix));
            $next = ((int) $suffix) + 1;
        }

        return $prefix . str_pad((string) $next, 3, '0', STR_PAD_LEFT);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Persediaan;

use App\Models\Persediaan\PersediaanBarang;
use App\Models\Persediaan\PersediaanQrAkses;
use App\Models\Persediaan\PersediaanRak;
use App\Models\Persediaan\PersediaanRakBarang;
use App\Models\Persediaan\PersediaanRakMovement;
use App\Support\Persediaan\PublicUploadPath;
use App\Support\Persediaan\RackAccess;
use App\Support\Persediaan\UnitKerja;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class PersediaanRackService
{
    public function __construct(
        private QrImageService $qr,
        private StockService $stock,
    ) {
    }

    public function generateToken(): string
    {
        do {
            $token = Str::lower(Str::random(40));
        } while (
            PersediaanRak::where('qr_token', $token)->exists()
            || PersediaanQrAkses::where('token', $token)->exists()
        );

        return $token;
    }

    public function rakPublicUrl(string $token): string
    {
        return url('/public/persediaan/rak/' . $token);
    }

    public function aksesPublicUrl(string $token): string
    {
        return url('/public/persediaan/akses/' . $token);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function createRak(array $data, ?int $userId): PersediaanRak
    {
        $unit = (string) $data['unit_kerja'];
        RackAccess::assertUnitAllowed($unit);

        return PersediaanRak::create([
            'kode_rak'      => strtoupper(trim((string) $data['kode_rak'])),
            'nama_rak'      => trim((string) $data['nama_rak']),
            'unit_kerja'    => $unit,
            'lokasi_detail' => $this->nullable($data['lokasi_detail'] ?? null),
            'qr_token'      => $this->generateToken(),
            'status'        => PersediaanRak::STATUS_AKTIF,
            'created_by'    => $userId,
            'updated_by'    => $userId,
        ]);
    }

    /**
     * @param  array<string,mixed>  $data
     */
    public function updateRak(PersediaanRak $rak, array $data, ?int $userId): PersediaanRak
    {
        $unit = (string) ($data['unit_kerja'] ?? $rak->unit_kerja);
        RackAccess::assertUnitAllowed($unit);

        $rak->kode_rak = strtoupper(trim((string) $data['kode_rak']));
        $rak->nama_rak = trim((string) $data['nama_rak']);
        $rak->unit_kerja = $unit;
        $rak->lokasi_detail = $this->nullable($data['lokasi_detail'] ?? null);
        $rak->status = ((string) ($data['status'] ?? $rak->status)) === PersediaanRak::STATUS_NONAKTIF
            ? PersediaanRak::STATUS_NONAKTIF
            : PersediaanRak::STATUS_AKTIF;
        $rak->updated_by = $userId;
        $rak->save();

        return $rak;
    }

    public function generateRakQr(PersediaanRak $rak): string
    {
        if ($rak->qr_token === null || $rak->qr_token === '') {
            $rak->qr_token = $this->generateToken();
            $rak->save();
        }

        $tokenBefore = (string) $rak->qr_token;
        $png = $this->qr->pngForUrl($this->rakPublicUrl($tokenBefore), 480);
        $relative = PublicUploadPath::QR_RAK_REL_DIR . '/rak-' . $rak->id . '.png';
        $this->qr->saveRelative($relative, $png);

        $rak->refresh();
        if ((string) $rak->qr_token !== $tokenBefore) {
            throw new \RuntimeException('Token QR rak berubah secara tidak terduga. Generate dibatalkan.');
        }

        $rak->qr_path = $relative;
        $rak->save();

        return $relative;
    }

    public function rotateRakToken(PersediaanRak $rak, ?int $userId): string
    {
        $rak->qr_token = $this->generateToken();
        $rak->updated_by = $userId;
        $rak->save();

        return $this->generateRakQr($rak);
    }

    public function ensureAkses(string $unitKerja, ?int $userId): PersediaanQrAkses
    {
        RackAccess::assertUnitAllowed($unitKerja);

        $akses = PersediaanQrAkses::query()
            ->where('unit_kerja', $unitKerja)
            ->orderByDesc('id')
            ->first();

        if ($akses === null) {
            $akses = PersediaanQrAkses::create([
                'unit_kerja' => $unitKerja,
                'nama'       => 'QR Pintu Persediaan ' . UnitKerja::label($unitKerja),
                'token'      => $this->generateToken(),
                'is_active'  => true,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
        }

        return $akses;
    }

    public function generateAksesQr(PersediaanQrAkses $akses): string
    {
        if ($akses->token === null || $akses->token === '') {
            $akses->token = $this->generateToken();
            $akses->save();
        }

        $tokenBefore = (string) $akses->token;
        $png = $this->qr->pngForUrl($this->aksesPublicUrl($tokenBefore), 520);
        $relative = PublicUploadPath::QR_AKSES_REL_DIR . '/akses-' . $akses->id . '.png';
        $this->qr->saveRelative($relative, $png);

        $akses->refresh();
        if ((string) $akses->token !== $tokenBefore) {
            throw new \RuntimeException('Token QR pintu berubah secara tidak terduga. Generate dibatalkan.');
        }

        $akses->qr_path = $relative;
        $akses->save();

        return $relative;
    }

    public function rotateAksesToken(PersediaanQrAkses $akses, ?int $userId): string
    {
        $akses->token = $this->generateToken();
        $akses->updated_by = $userId;
        $akses->save();

        return $this->generateAksesQr($akses);
    }

    public function qrFileUrl(?string $relativePath): ?string
    {
        $relative = PublicUploadPath::normalizeRelative($relativePath);
        if ($relative === null) {
            return null;
        }

        $absolute = PublicUploadPath::firstExistingFile($relative);
        if ($absolute === null) {
            return null;
        }

        $url = PublicUploadPath::publicUrl($relative);
        $mtime = @filemtime($absolute);
        if ($mtime) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 't=' . $mtime;
        }

        return $url !== '' ? $url : null;
    }

    /**
     * @param  list<int>  $barangIds
     */
    public function assignBarangs(PersediaanRak $rak, array $barangIds, ?int $userId): void
    {
        RackAccess::assertUnitAllowed((string) $rak->unit_kerja);

        $barangIds = array_values(array_unique(array_filter(array_map('intval', $barangIds))));
        if ($barangIds === []) {
            return;
        }

        $barangs = PersediaanBarang::query()->whereIn('id', $barangIds)->get()->keyBy('id');

        DB::transaction(function () use ($rak, $barangIds, $barangs, $userId) {
            foreach ($barangIds as $barangId) {
                $barang = $barangs->get($barangId);
                if ($barang === null) {
                    throw new InvalidArgumentException('Barang tidak ditemukan.');
                }
                if ((string) $barang->unit_kerja !== (string) $rak->unit_kerja) {
                    throw new InvalidArgumentException(
                        'Barang unit ' . $barang->unit_kerja . ' tidak boleh dimasukkan ke rak ' . $rak->unit_kerja . '.'
                    );
                }

                $current = PersediaanRakBarang::query()->where('barang_id', $barangId)->first();
                $fromRakId = $current?->rak_id;

                if ($current !== null && (int) $current->rak_id === (int) $rak->id) {
                    continue;
                }

                if ($current !== null) {
                    $current->delete();
                }

                PersediaanRakBarang::create([
                    'rak_id'      => $rak->id,
                    'barang_id'   => $barangId,
                    'assigned_at' => Carbon::now(),
                    'assigned_by' => $userId,
                ]);

                PersediaanRakMovement::create([
                    'barang_id'   => $barangId,
                    'from_rak_id' => $fromRakId,
                    'to_rak_id'   => $rak->id,
                    'moved_by'    => $userId,
                    'moved_at'    => Carbon::now(),
                    'catatan'     => $fromRakId ? 'Mutasi rak' : 'Penempatan rak',
                ]);
            }
        });
    }

    public function removeBarang(PersediaanRak $rak, int $barangId, ?int $userId): void
    {
        $row = PersediaanRakBarang::query()
            ->where('rak_id', $rak->id)
            ->where('barang_id', $barangId)
            ->first();

        if ($row === null) {
            return;
        }

        $row->delete();

        PersediaanRakMovement::create([
            'barang_id'   => $barangId,
            'from_rak_id' => $rak->id,
            'to_rak_id'   => $rak->id,
            'moved_by'    => $userId,
            'moved_at'    => Carbon::now(),
            'catatan'     => 'Dikeluarkan dari rak',
        ]);
    }

    public function moveBarang(int $barangId, int $toRakId, ?int $userId, ?string $catatan = null): void
    {
        $toRak = PersediaanRak::query()->whereKey($toRakId)->firstOrFail();
        $this->assignBarangs($toRak, [$barangId], $userId);

        if ($catatan !== null && $catatan !== '') {
            PersediaanRakMovement::query()
                ->where('barang_id', $barangId)
                ->where('to_rak_id', $toRakId)
                ->orderByDesc('id')
                ->limit(1)
                ->update(['catatan' => $catatan]);
        }
    }

    /**
     * @return list<array{barang: PersediaanBarang, stok: int}>
     */
    public function itemsWithStock(PersediaanRak $rak): array
    {
        $barangs = PersediaanBarang::query()
            ->where('is_active', true)
            ->where('unit_kerja', $rak->unit_kerja)
            ->whereIn('id', PersediaanRakBarang::query()->where('rak_id', $rak->id)->select('barang_id'))
            ->orderBy('nama_barang')
            ->get();

        $ringkasan = $this->stock->ringkasanBanyak($barangs);
        $rows = [];
        foreach ($barangs as $barang) {
            $rows[] = [
                'barang' => $barang,
                'stok'   => (int) ($ringkasan[(int) $barang->id]['akhir'] ?? $this->stock->stokAkhir($barang)),
            ];
        }

        return $rows;
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}

<?php

declare(strict_types=1);

namespace App\Support\BMN;

use App\Models\BMN\AsetBmn;
use App\Models\BMN\KategoriAset;
use App\Models\BMN\LokasiAset;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Support\Facades\Auth;

class AsetBmnAuditHelper
{
    /**
     * Atribut yang diaudit (selaras dengan kolom bisnis aset).
     *
     * @return array<int, string>
     */
    public static function auditableKeys(): array
    {
        return array_merge(['id'], (new AsetBmn())->getFillable());
    }

    /**
     * Ekstrak hanya field yang berubah dari model (getDirty).
     *
     * @return array{data_lama: array<string, mixed>, data_baru: array<string, mixed>}
     */
    public static function getChangedData(AsetBmn $model): array
    {
        $dirty = $model->getDirty();
        unset($dirty['updated_at'], $dirty['created_at']);

        $dataLama = [];
        $dataBaru = [];

        $allowed = array_flip(self::auditableKeys());

        foreach ($dirty as $key => $newValue) {
            if (! isset($allowed[$key])) {
                continue;
            }

            $dataBaru[$key] = self::normalizeAttributeForAudit($key, $newValue);
            $dataLama[$key] = self::normalizeAttributeForAudit($key, $model->getOriginal($key));
        }

        return [
            'data_lama' => $dataLama,
            'data_baru' => $dataBaru,
        ];
    }

    /**
     * Snapshot baris untuk audit (nilai saat ini setelah persist).
     *
     * @return array<string, mixed>
     */
    public static function snapshotCurrent(AsetBmn $model): array
    {
        $out = [];
        foreach (self::auditableKeys() as $key) {
            if (! array_key_exists($key, $model->getAttributes())) {
                continue;
            }
            $out[$key] = self::normalizeAttributeForAudit($key, $model->getAttribute($key));
        }

        return $out;
    }

    /**
     * Snapshot dari nilai original sebelum perubahan (getOriginal).
     *
     * @return array<string, mixed>
     */
    public static function snapshotOriginal(AsetBmn $model): array
    {
        $out = [];
        foreach (self::auditableKeys() as $key) {
            if (! array_key_exists($key, $model->getOriginal())) {
                continue;
            }
            $out[$key] = self::normalizeAttributeForAudit($key, $model->getOriginal($key));
        }

        return $out;
    }

    /**
     * Deskripsi human-readable untuk perubahan parsial.
     */
    public static function describeUpdate(array $dataLama, array $dataBaru): string
    {
        if ($dataBaru === []) {
            return 'Tidak ada perubahan field yang dicatat.';
        }

        $keys = implode(', ', array_keys($dataBaru));
        $parts = [];
        foreach ($dataBaru as $key => $newVal) {
            $oldVal = $dataLama[$key] ?? null;
            $parts[] = self::describeFieldTransition($key, $oldVal, $newVal);
        }

        return 'Perubahan pada: '.$keys.'. Update aset: '.implode(', ', $parts);
    }

    public static function describeFieldTransition(string $key, mixed $oldVal, mixed $newVal): string
    {
        $oldLabel = self::humanizeValue($key, $oldVal);
        $newLabel = self::humanizeValue($key, $newVal);

        return sprintf(
            "%s dari '%s' ke '%s'",
            $key,
            self::stringifyForSentence($oldLabel),
            self::stringifyForSentence($newLabel)
        );
    }

    /**
     * User id aman untuk audit; null dianggap sistem.
     */
    public static function auditUserId(): ?int
    {
        if (! Auth::check()) {
            return null;
        }

        $id = Auth::id();

        return $id === null ? null : (int) $id;
    }

    /**
     * Normalisasi nilai untuk JSON audit (JSON-serializable).
     */
    public static function normalizeAttributeForAudit(string $key, mixed $value): mixed
    {
        if ($value instanceof CarbonInterface || $value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        if ($key === 'tanggal_perolehan' && is_string($value) && $value !== '') {
            return substr($value, 0, 10);
        }

        if (is_numeric($value) && in_array($key, ['id', 'kategori_id', 'lokasi_id'], true)) {
            return (int) $value;
        }

        if ($key === 'nilai_perolehan' && is_numeric($value)) {
            return round((float) $value, 2);
        }

        return $value;
    }

    protected static function humanizeValue(string $key, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '(kosong)';
        }

        if ($key === 'lokasi_id') {
            $row = LokasiAset::query()->find($value);

            return $row ? $row->nama_lokasi.' [id='.$value.']' : (string) $value;
        }

        if ($key === 'kategori_id') {
            $row = KategoriAset::query()->find($value);

            return $row ? $row->nama_kategori.' [id='.$value.']' : (string) $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    protected static function stringifyForSentence(mixed $value): string
    {
        $s = (string) $value;
        if (strlen($s) > 120) {
            return substr($s, 0, 117).'...';
        }

        return $s;
    }
}

<?php

declare(strict_types=1);

namespace App\Support\Persediaan;

/**
 * Sumber kebenaran tunggal untuk daftar unit kerja Persediaan
 * beserta kode (untuk nomor permintaan) dan slug (untuk URL).
 */
final class UnitKerja
{
    public const KEPERAWATAN = 'Keperawatan';
    public const KEBIDANAN = 'Kebidanan';
    public const TLM = 'TLM';
    public const DIREKTORAT = 'Direktorat';
    public const PPPK = 'PPPK';
    public const PIMPINAN = 'Pimpinan';

    /** @var array<string,string> nama unit => kode permintaan */
    public const KODE = [
        self::KEPERAWATAN => 'KEP',
        self::KEBIDANAN   => 'BID',
        self::TLM         => 'TLM',
        self::DIREKTORAT  => 'DIR',
        self::PPPK        => 'PPP',
        self::PIMPINAN    => 'PIM',
    ];

    /** @var array<string,string> slug url => nama unit */
    public const SLUG = [
        'keperawatan' => self::KEPERAWATAN,
        'kebidanan'   => self::KEBIDANAN,
        'tlm'         => self::TLM,
        'direktorat'  => self::DIREKTORAT,
        'pppk'        => self::PPPK,
        'pimpinan'    => self::PIMPINAN,
    ];

    /** @var array<string,string> nama unit => label tampilan */
    public const LABEL = [
        self::KEPERAWATAN => 'Jurusan Keperawatan',
        self::KEBIDANAN   => 'Jurusan Kebidanan',
        self::TLM         => 'Jurusan Analis (TLM)',
        self::DIREKTORAT  => 'Direktorat',
        self::PPPK        => 'PPPK',
        self::PIMPINAN    => 'Pimpinan',
    ];

    /** Unit yang ditampilkan di halaman publik pemohon (pilih unit kerja). */
    public const PUBLIC = [
        self::KEPERAWATAN,
        self::KEBIDANAN,
        self::TLM,
        self::DIREKTORAT,
    ];

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::KODE);
    }

    /** @return list<string> */
    public static function forPublic(): array
    {
        return self::PUBLIC;
    }

    public static function isValid(?string $unit): bool
    {
        return $unit !== null && array_key_exists($unit, self::KODE);
    }

    public static function fromSlug(?string $slug): ?string
    {
        if ($slug === null) {
            return null;
        }

        return self::SLUG[strtolower($slug)] ?? null;
    }

    public static function slugOf(string $unit): string
    {
        return array_search($unit, self::SLUG, true) ?: strtolower($unit);
    }

    public static function kode(string $unit): string
    {
        return self::KODE[$unit] ?? 'UMM';
    }

    public static function label(string $unit): string
    {
        return self::LABEL[$unit] ?? $unit;
    }
}

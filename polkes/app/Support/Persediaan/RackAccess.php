<?php

declare(strict_types=1);

namespace App\Support\Persediaan;

use App\Models\Persediaan\PersediaanPemohon;
use App\Models\Persediaan\PersediaanRak;
use Illuminate\Http\Request;

/**
 * Feature flag + otorisasi sistem rak. Saat ini hanya Direktorat.
 */
final class RackAccess
{
    /** @return list<string> */
    public static function enabledUnits(): array
    {
        $units = config('persediaan.rack_enabled_units', [UnitKerja::DIREKTORAT]);

        return is_array($units) ? array_values($units) : [UnitKerja::DIREKTORAT];
    }

    public static function isEnabledForUnit(?string $unitKerja): bool
    {
        if ($unitKerja === null || $unitKerja === '') {
            return false;
        }

        return in_array($unitKerja, self::enabledUnits(), true);
    }

    public static function assertUnitAllowed(string $unitKerja): void
    {
        if (! self::isEnabledForUnit($unitKerja)) {
            abort(422, 'Sistem rak hanya aktif untuk unit ' . implode(', ', self::enabledUnits()) . '.');
        }
    }

    public static function adminCanManage(Request $request): bool
    {
        if (PersediaanAccess::isSuperadmin($request)) {
            return true;
        }

        $locked = PersediaanAccess::lockedUnitForApprover($request);

        return $locked !== null && self::isEnabledForUnit($locked);
    }

    public static function pemohonCanUseRak(PersediaanPemohon $pemohon, PersediaanRak $rak): bool
    {
        if (! $rak->isAktif()) {
            return false;
        }

        if (! self::isEnabledForUnit((string) $rak->unit_kerja)) {
            return false;
        }

        return (string) $pemohon->unit_kerja === (string) $rak->unit_kerja;
    }
}

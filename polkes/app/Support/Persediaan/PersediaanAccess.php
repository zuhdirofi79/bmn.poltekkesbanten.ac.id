<?php

declare(strict_types=1);

namespace App\Support\Persediaan;

use App\Models\Persediaan\PersediaanPermintaan;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * Akses admin persediaan & mapping approver unit kerja (config eksplisit).
 */
final class PersediaanAccess
{
    /** @return array<string, array{user_id: int, email: string, name: string}> */
    public static function approverConfig(): array
    {
        return config('persediaan.approvers', []);
    }

    /** @return list<int> */
    public static function superadminUserIds(): array
    {
        return array_map('intval', config('persediaan.superadmin_user_ids', []));
    }

    public static function isSuperadminUserId(int $userId): bool
    {
        return in_array($userId, self::superadminUserIds(), true);
    }

    public static function isSuperadmin(Request $request): bool
    {
        $userId = PersediaanAdminSession::userId($request);

        return $userId !== null && self::isSuperadminUserId($userId);
    }

    /** Unit kerja yang user ini boleh approve (null jika bukan approver unit). */
    public static function approverUnitForUserId(int $userId): ?string
    {
        foreach (self::approverConfig() as $unit => $row) {
            if ((int) ($row['user_id'] ?? 0) === $userId) {
                return $unit;
            }
        }

        return null;
    }

    public static function hasAdminAccess(int $userId): bool
    {
        if (self::isSuperadminUserId($userId)) {
            return true;
        }

        return self::approverUnitForUserId($userId) !== null;
    }

    public static function approverUserIdForUnit(string $unitKerja): ?int
    {
        $row = self::approverConfig()[$unitKerja] ?? null;
        if ($row === null) {
            return null;
        }

        return (int) $row['user_id'];
    }

    public static function approverNameForUnit(string $unitKerja): ?string
    {
        $row = self::approverConfig()[$unitKerja] ?? null;

        return $row['name'] ?? null;
    }

    public static function getPersediaanApproverByUnit(string $unitKerja): ?User
    {
        $row = self::approverConfig()[$unitKerja] ?? null;
        if ($row === null) {
            return null;
        }

        $userId = (int) ($row['user_id'] ?? 0);
        $email = strtolower(trim((string) ($row['email'] ?? '')));

        $user = User::query()->whereKey($userId)->first();
        if ($user !== null && $email !== '' && strtolower((string) $user->email) === $email) {
            return $user;
        }

        if ($user !== null) {
            return $user;
        }

        if ($email !== '') {
            return User::query()->whereRaw('LOWER(email) = ?', [$email])->first();
        }

        return null;
    }

    public static function canViewPermintaan(int $userId, PersediaanPermintaan $permintaan): bool
    {
        if (self::isSuperadminUserId($userId)) {
            return true;
        }

        $unit = self::approverUnitForUserId($userId);
        if ($unit !== null && $permintaan->unit_kerja === $unit) {
            return true;
        }

        $assigned = (int) ($permintaan->approver_user_id ?? 0);

        return $assigned > 0 && $assigned === $userId;
    }

    public static function canApprovePermintaan(int $userId, PersediaanPermintaan $permintaan): bool
    {
        if (! $permintaan->isPending()) {
            return false;
        }

        if (self::isSuperadminUserId($userId)) {
            return true;
        }

        $assigned = (int) ($permintaan->approver_user_id ?? 0);

        return $assigned > 0 && $assigned === $userId;
    }

    /** Unit kerja efektif untuk filter data admin (null = semua unit). */
    public static function effectiveUnitFilter(Request $request): ?string
    {
        if (self::isSuperadmin($request)) {
            $unit = $request->input('unit');

            return is_string($unit) && $unit !== '' ? $unit : null;
        }

        $userId = PersediaanAdminSession::userId($request);
        if ($userId === null) {
            return '__denied__';
        }

        return self::approverUnitForUserId($userId)
            ?? PersediaanAdminSession::activeAdmin($request)?->effectiveSatker()
            ?? '__denied__';
    }

    /**
     * @param  Builder<PersediaanPermintaan>  $query
     * @return Builder<PersediaanPermintaan>
     */
    public static function scopePermintaanQuery(Builder $query, Request $request): Builder
    {
        $unit = self::effectiveUnitFilter($request);

        if ($unit === '__denied__') {
            return $query->whereRaw('1 = 0');
        }

        if ($unit !== null) {
            $query->where('unit_kerja', $unit);
        }

        return $query;
    }

    public static function lockedUnitForApprover(Request $request): ?string
    {
        if (self::isSuperadmin($request)) {
            return null;
        }

        $userId = PersediaanAdminSession::userId($request);
        if ($userId === null) {
            return null;
        }

        return self::approverUnitForUserId($userId);
    }
}

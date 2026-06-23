<?php

declare(strict_types=1);

namespace App\Support\BhpKebersihan;

use App\Models\BhpKebersihan\BhpKebersihanAdmin;
use Illuminate\Http\Request;

/**
 * Akses admin BHP Kebersihan berdasarkan role di tabel bhp_kebersihan_admins.
 */
final class BhpAccess
{
    /** @return list<int> */
    public static function superadminUserIds(): array
    {
        return array_map('intval', config('bhp_kebersihan.superadmin_user_ids', []));
    }

    public static function isSuperadminUserId(int $userId): bool
    {
        return in_array($userId, self::superadminUserIds(), true);
    }

    public static function hasAdminAccess(int $userId): bool
    {
        if (self::isSuperadminUserId($userId)) {
            return true;
        }

        return BhpKebersihanAdmin::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->exists();
    }

    public static function roleForUserId(int $userId): ?string
    {
        if (self::isSuperadminUserId($userId)) {
            return BhpKebersihanAdmin::ROLE_SUPERADMIN;
        }

        return BhpKebersihanAdmin::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->value('role');
    }

    public static function isSuperadmin(Request $request): bool
    {
        $role = BhpAdminSession::role($request);

        return $role === BhpKebersihanAdmin::ROLE_SUPERADMIN;
    }

    /**
     * @return list<string>
     */
    public static function allowedMenus(?string $role): array
    {
        if ($role === BhpKebersihanAdmin::ROLE_SUPERADMIN) {
            return [
                'dashboard', 'permintaan', 'pengambilan', 'stok-akhir',
                'opname', 'laporan', 'master-barang', 'pengaturan',
            ];
        }

        return match ($role) {
            BhpKebersihanAdmin::ROLE_PENGAMBILAN => ['permintaan', 'pengambilan'],
            BhpKebersihanAdmin::ROLE_MASTER => [
                'dashboard', 'stok-akhir', 'opname', 'laporan', 'master-barang', 'pengaturan',
            ],
            BhpKebersihanAdmin::ROLE_VERIFIKATOR => ['dashboard', 'permintaan', 'laporan'],
            default => [
                'dashboard', 'permintaan', 'stok-akhir', 'opname',
                'laporan', 'master-barang', 'pengaturan',
            ],
        };
    }

    public static function canAccessMenu(?string $role, string $menu): bool
    {
        if ($role === BhpKebersihanAdmin::ROLE_SUPERADMIN) {
            return true;
        }

        return in_array($menu, self::allowedMenus($role), true);
    }

    public static function canAccessRoute(?string $role, ?string $routeName): bool
    {
        if ($routeName === null || $routeName === 'bhp.admin.logout') {
            return true;
        }

        if ($role === BhpKebersihanAdmin::ROLE_SUPERADMIN) {
            return true;
        }

        if (! str_starts_with($routeName, 'bhp.admin.')) {
            return true;
        }

        $menu = self::routeToMenu($routeName);

        return $menu === null || self::canAccessMenu($role, $menu);
    }

    private static function routeToMenu(string $routeName): ?string
    {
        if (str_contains($routeName, '.dashboard')) {
            return 'dashboard';
        }
        if (str_contains($routeName, '.permintaan')) {
            return 'permintaan';
        }
        if (str_contains($routeName, '.pengambilan')) {
            return 'pengambilan';
        }
        if (str_contains($routeName, '.stok-akhir')) {
            return 'stok-akhir';
        }
        if (str_contains($routeName, '.opname')) {
            return 'opname';
        }
        if (str_contains($routeName, '.laporan')) {
            return 'laporan';
        }
        if (str_contains($routeName, '.master-barang')) {
            return 'master-barang';
        }
        if (str_contains($routeName, '.pengaturan')) {
            return 'pengaturan';
        }

        return null;
    }

    public static function canVerify(?string $role): bool
    {
        return in_array($role, [
            BhpKebersihanAdmin::ROLE_SUPERADMIN,
            BhpKebersihanAdmin::ROLE_VERIFIKATOR,
        ], true);
    }

    public static function canProcessPickup(?string $role): bool
    {
        return in_array($role, [
            BhpKebersihanAdmin::ROLE_SUPERADMIN,
            BhpKebersihanAdmin::ROLE_PENGAMBILAN,
        ], true);
    }

    public static function canManageMaster(?string $role): bool
    {
        return in_array($role, [
            BhpKebersihanAdmin::ROLE_SUPERADMIN,
            BhpKebersihanAdmin::ROLE_MASTER,
        ], true);
    }
}

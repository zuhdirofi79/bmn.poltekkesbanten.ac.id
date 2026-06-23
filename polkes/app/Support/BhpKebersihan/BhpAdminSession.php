<?php

declare(strict_types=1);

namespace App\Support\BhpKebersihan;

use App\Models\BhpKebersihan\BhpKebersihanAdmin;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Session khusus Admin BHP Kebersihan — terpisah dari guard web (admin BMN utama).
 */
final class BhpAdminSession
{
    public const KEY_USER_ID = 'bhp_admin_id';
    public const KEY_ROLE = 'bhp_admin_role';

    public static function isLoggedIn(Request $request): bool
    {
        return $request->session()->has(self::KEY_USER_ID);
    }

    public static function userId(Request $request): ?int
    {
        $id = $request->session()->get(self::KEY_USER_ID);

        return $id !== null ? (int) $id : null;
    }

    public static function role(Request $request): ?string
    {
        return $request->session()->get(self::KEY_ROLE);
    }

    public static function isSuperadmin(Request $request): bool
    {
        return self::role($request) === BhpKebersihanAdmin::ROLE_SUPERADMIN;
    }

    public static function login(Request $request, BhpKebersihanAdmin $admin, User $user): void
    {
        $request->session()->regenerate();

        $role = BhpAccess::isSuperadminUserId((int) $user->id)
            ? BhpKebersihanAdmin::ROLE_SUPERADMIN
            : $admin->role;

        $request->session()->put([
            self::KEY_USER_ID => (int) $user->id,
            self::KEY_ROLE    => $role,
        ]);
    }

    public static function logout(Request $request): void
    {
        $request->session()->forget([
            self::KEY_USER_ID,
            self::KEY_ROLE,
        ]);
    }

    public static function displayName(Request $request): string
    {
        $userId = self::userId($request);
        if ($userId === null) {
            return 'Admin';
        }

        return User::query()->whereKey($userId)->value('name') ?: 'Admin';
    }

    /**
     * @return array{user: User, admin: BhpKebersihanAdmin}|null
     */
    public static function attemptCredentials(string $username, string $password): ?array
    {
        $user = User::query()->where('username', $username)->first();
        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            return null;
        }

        if (! BhpAccess::hasAdminAccess((int) $user->id)) {
            return null;
        }

        $admin = BhpKebersihanAdmin::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($admin === null && BhpAccess::isSuperadminUserId((int) $user->id)) {
            $admin = new BhpKebersihanAdmin([
                'user_id'   => (int) $user->id,
                'role'      => BhpKebersihanAdmin::ROLE_SUPERADMIN,
                'is_active' => true,
            ]);
        }

        if ($admin === null) {
            return null;
        }

        return ['user' => $user, 'admin' => $admin];
    }

    public static function activeAdmin(Request $request): ?BhpKebersihanAdmin
    {
        $userId = self::userId($request);
        if ($userId === null) {
            return null;
        }

        return BhpKebersihanAdmin::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    public static function resolveUser(Request $request): ?User
    {
        $userId = self::userId($request);
        if ($userId !== null) {
            return User::query()->whereKey($userId)->first();
        }

        return auth()->user() ?? $request->user();
    }
}

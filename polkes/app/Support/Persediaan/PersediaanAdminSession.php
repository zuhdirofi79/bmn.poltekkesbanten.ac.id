<?php

declare(strict_types=1);

namespace App\Support\Persediaan;

use App\Models\Persediaan\PersediaanAdmin;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

/**
 * Session khusus Admin Persediaan — terpisah dari guard web (admin BMN utama).
 */
final class PersediaanAdminSession
{
    public const KEY_USER_ID = 'persediaan_admin_id';
    public const KEY_ROLE = 'persediaan_admin_role';
    public const KEY_SATKER = 'persediaan_admin_satker';

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

    public static function satker(Request $request): ?string
    {
        $satker = $request->session()->get(self::KEY_SATKER);

        return $satker !== null && $satker !== '' ? (string) $satker : null;
    }

    public static function isSuperadmin(Request $request): bool
    {
        return self::role($request) === PersediaanAdmin::ROLE_SUPERADMIN;
    }

    public static function login(Request $request, ?PersediaanAdmin $admin, User $user): void
    {
        $request->session()->regenerate();

        $role = $admin?->role;
        $satker = $admin?->satker;

        if (PersediaanAccess::isSuperadminUserId((int) $user->id)) {
            $role = PersediaanAdmin::ROLE_SUPERADMIN;
            $satker = null;
        } elseif ($unit = PersediaanAccess::approverUnitForUserId((int) $user->id)) {
            $satker = $unit;
            if ($role === null) {
                $role = match ($unit) {
                    'Keperawatan' => PersediaanAdmin::ROLE_KEPERAWATAN,
                    'Kebidanan'   => PersediaanAdmin::ROLE_KEBIDANAN,
                    'TLM'         => PersediaanAdmin::ROLE_TLM,
                    'Direktorat'  => PersediaanAdmin::ROLE_DIREKTORAT,
                    default       => 'admin_persediaan_unit',
                };
            }
        }

        $request->session()->put([
            self::KEY_USER_ID => (int) $user->id,
            self::KEY_ROLE    => $role,
            self::KEY_SATKER  => $satker,
        ]);
    }

    public static function logout(Request $request): void
    {
        $request->session()->forget([
            self::KEY_USER_ID,
            self::KEY_ROLE,
            self::KEY_SATKER,
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
     * @return array{user: User, admin: PersediaanAdmin}|null
     */
    public static function attemptCredentials(string $username, string $password): ?array
    {
        $user = User::query()->where('username', $username)->first();
        if ($user === null || ! Hash::check($password, (string) $user->password)) {
            return null;
        }

        $admin = PersediaanAdmin::query()
            ->where('user_id', $user->id)
            ->where('is_active', true)
            ->first();

        if ($admin === null && ! PersediaanAccess::hasAdminAccess((int) $user->id)) {
            return null;
        }

        if ($admin === null) {
            $admin = PersediaanAdmin::query()
                ->where('user_id', $user->id)
                ->where('is_active', true)
                ->first();
        }

        return ['user' => $user, 'admin' => $admin];
    }

    public static function activeAdmin(Request $request): ?PersediaanAdmin
    {
        $userId = self::userId($request);
        if ($userId === null) {
            return null;
        }

        return PersediaanAdmin::query()
            ->where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Resolver admin persediaan — session khusus diutamakan, lalu guard.
     */
    public static function resolveUser(Request $request): ?User
    {
        $userId = self::userId($request);
        if ($userId !== null) {
            return User::query()->whereKey($userId)->first();
        }

        return auth('persediaan_admin')->user()
            ?? auth()->user()
            ?? $request->user();
    }
}

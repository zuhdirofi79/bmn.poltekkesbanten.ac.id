<?php

declare(strict_types=1);

namespace App\Models\BhpKebersihan;

use Illuminate\Database\Eloquent\Model;

class BhpKebersihanAdmin extends Model
{
    public const ROLE_SUPERADMIN = 'superadmin_bhp';
    public const ROLE_MASTER = 'admin_bhp_master';
    public const ROLE_VERIFIKATOR = 'admin_bhp_verifikator';
    public const ROLE_PENGAMBILAN = 'admin_bhp_pengambilan';

    protected $table = 'bhp_kebersihan_admins';

    protected $fillable = [
        'user_id',
        'role',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            self::ROLE_SUPERADMIN   => 'Superadmin BHP',
            self::ROLE_MASTER       => 'Admin Master BHP',
            self::ROLE_VERIFIKATOR  => 'Verifikator BHP',
            self::ROLE_PENGAMBILAN  => 'Pengambilan BHP',
            default                 => $this->role,
        };
    }
}

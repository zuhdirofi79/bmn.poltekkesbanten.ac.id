<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;

class PersediaanAdmin extends Model
{
    public const ROLE_SUPERADMIN = 'superadmin_persediaan';
    public const ROLE_KEPERAWATAN = 'admin_persediaan_keperawatan';
    public const ROLE_KEBIDANAN = 'admin_persediaan_kebidanan';
    public const ROLE_TLM = 'admin_persediaan_tlm';
    public const ROLE_DIREKTORAT = 'admin_persediaan_direktorat';
    public const ROLE_PPPK = 'admin_persediaan_pppk';
    public const ROLE_PIMPINAN = 'admin_persediaan_pimpinan';

    /** @var array<string, string|null> role => satker (null = semua untuk superadmin) */
    public const ROLE_SATKER = [
        self::ROLE_SUPERADMIN   => null,
        self::ROLE_KEPERAWATAN => 'Keperawatan',
        self::ROLE_KEBIDANAN   => 'Kebidanan',
        self::ROLE_TLM         => 'TLM',
        self::ROLE_DIREKTORAT  => 'Direktorat',
        self::ROLE_PPPK        => 'PPPK',
        self::ROLE_PIMPINAN    => 'Pimpinan',
    ];

    protected $table = 'persediaan_admins';

    protected $fillable = [
        'user_id',
        'role',
        'satker',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    /** Satker efektif untuk filter data (null = semua satker). */
    public function effectiveSatker(): ?string
    {
        if ($this->isSuperadmin()) {
            return null;
        }

        if ($this->satker !== null && $this->satker !== '') {
            return $this->satker;
        }

        return self::ROLE_SATKER[$this->role] ?? null;
    }
}

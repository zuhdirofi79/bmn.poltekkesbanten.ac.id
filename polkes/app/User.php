<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use Notifiable;
    use HasRoles;
    protected $notify;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 'email', 'password', 'username', 'foto_profile', 'jenis_kelamin', 'no_hp','jurusan','role','can_approve_bmn'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'can_approve_bmn' => 'boolean',
    ];

    /**
     * Daftar jurusan yang berhak mengajukan peminjaman aset BMN.
     * Disimpan lowercase agar konsisten dengan data legacy.
     */
    public const BMN_JURUSAN_REQUESTERS = [
        'direktorat',
        'tlm',
        'keperawatan',
        'kebidanan',
    ];

    /** Verifikator 1 — Pak Hendri */
    public const BMN_VERIFIER_1_ID = 3235;

    /** Verifikator 2 — Pak Singgih */
    public const BMN_VERIFIER_2_ID = 3240;

    /**
     * Apakah user boleh mengajukan request peminjaman aset BMN.
     *
     * Aturan (terpisah dari approval):
     *   - role spatie = "jurusan"  ATAU
     *   - kolom `jurusan` user berada dalam whitelist BMN_JURUSAN_REQUESTERS.
     */
    public function canRequestBmn(): bool
    {
        if ($this->hasRole('jurusan')) {
            return true;
        }

        $jurusan = strtolower((string) ($this->jurusan ?? ''));

        return $jurusan !== '' && in_array($jurusan, self::BMN_JURUSAN_REQUESTERS, true);
    }

    /**
     * Apakah user adalah approval officer BMN.
     * Wajib admin AND flag can_approve_bmn = true.
     */
    public function canApproveBmn(): bool
    {
        return $this->hasRole('admin') && (bool) $this->can_approve_bmn === true;
    }

    public function isBmnVerifier1(): bool
    {
        return (int) $this->id === self::BMN_VERIFIER_1_ID;
    }

    public function isBmnVerifier2(): bool
    {
        return (int) $this->id === self::BMN_VERIFIER_2_ID;
    }

    public function isBmnApprovalParticipant(): bool
    {
        return in_array((int) $this->id, [self::BMN_VERIFIER_1_ID, self::BMN_VERIFIER_2_ID], true);
    }
}

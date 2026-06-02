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

    /** Rumah Tangga BMN — Pak Munawir */
    public const BMN_RUMAH_TANGGA_MUNAWIR_ID = 3239;

    /** Rumah Tangga BMN — Pak Singgih */
    public const BMN_RUMAH_TANGGA_SINGGIH_ID = 3240;

    /** @var list<int> */
    public const BMN_RUMAH_TANGGA_IDS = [3239, 3240];

    /** Admin Sewa BMN — Bu Indah (verifikasi pembayaran BLU) */
    public const SEWA_ADMIN_INDAH_ID = 3241;
    public const SEWA_ADMIN_INDAH_USERNAME = 'Indah_Tryana';

    /** Admin Sewa BMN — Bu Retno (koordinasi penyewa) */
    public const SEWA_ADMIN_RETNO_ID = 3242;
    public const SEWA_ADMIN_RETNO_USERNAME = 'Retno_Pintoko';

    /**
     * Nomor WA publik Bu Retno (untuk ditampilkan ke penyewa).
     * Format internasional tanpa simbol (62...) untuk wa.me, plus format display.
     */
    public const SEWA_ADMIN_RETNO_WA_DISPLAY = '0857-1203-8910';
    public const SEWA_ADMIN_RETNO_WA_INTL = '6285712038910';

    /** Admin Sewa BMN — Pak Singgih (koordinasi teknis/aset) */
    public const SEWA_ADMIN_SINGGIH_ID = 3240;
    public const SEWA_ADMIN_SINGGIH_USERNAME = '199705042025211037';

    /** @var list<int> id semua admin Sewa BMN */
    public const SEWA_ADMIN_IDS = [3240, 3241, 3242];

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

    public function isRumahTanggaBmn(): bool
    {
        return in_array((int) $this->id, self::BMN_RUMAH_TANGGA_IDS, true);
    }

    public function isFinalBmnApprover(): bool
    {
        return (int) $this->id === self::BMN_VERIFIER_1_ID;
    }

    /**
     * Apakah user adalah salah satu Admin Sewa BMN
     * (Bu Indah / Bu Retno / Pak Singgih).
     */
    public function canManageSewaBmn(): bool
    {
        return in_array((int) $this->id, self::SEWA_ADMIN_IDS, true);
    }

    /**
     * Bu Indah — satu-satunya yang berwenang memverifikasi
     * pembayaran sewa ke rekening BLU.
     */
    public function canVerifySewaPayment(): bool
    {
        return (int) $this->id === self::SEWA_ADMIN_INDAH_ID
            && $this->username === self::SEWA_ADMIN_INDAH_USERNAME;
    }

    /**
     * Bu Retno + Pak Singgih — koordinator penyewa s/d hari H.
     */
    public function canCoordinateSewa(): bool
    {
        return in_array((int) $this->id, [self::SEWA_ADMIN_RETNO_ID, self::SEWA_ADMIN_SINGGIH_ID], true);
    }

    public function isRetnoSewaCoordinator(): bool
    {
        return (int) $this->id === self::SEWA_ADMIN_RETNO_ID
            && $this->username === self::SEWA_ADMIN_RETNO_USERNAME;
    }

    public function isSinggihSewaCoordinator(): bool
    {
        return (int) $this->id === self::SEWA_ADMIN_SINGGIH_ID
            && $this->username === self::SEWA_ADMIN_SINGGIH_USERNAME;
    }

    public function isIndahSewaVerifier(): bool
    {
        return $this->canVerifySewaPayment();
    }
}

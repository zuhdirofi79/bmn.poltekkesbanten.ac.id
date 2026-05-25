<?php

declare(strict_types=1);

namespace App\Models\BMN;

use App\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pengembalian aset BMN. 1-to-1 dengan PeminjamanAsetBmn.
 *
 * Catatan: tabel pengembalian saat ini TIDAK menyimpan kolom kondisi_kembali.
 * Kondisi pengembalian per item disimpan di peminjaman_aset_bmn_detail.kondisi_kembali.
 *
 * @property int $id
 * @property int $peminjaman_aset_bmn_id
 * @property int|null $diterima_by
 * @property \Illuminate\Support\Carbon $tanggal_kembali
 * @property string|null $catatan_pengembalian
 * @property string|null $bukti_foto_path
 * @property string|null $bukti_foto_original_name
 */
class PengembalianAsetBmn extends Model
{
    protected $table = 'pengembalian_aset_bmn';

    public const SOURCE_LOGIN = 'login';

    public const SOURCE_PUBLIC = 'public';

    public const ADMIN_STATUS_PENDING = 'pending';

    public const ADMIN_STATUS_APPROVED = 'approved';

    public const ADMIN_STATUS_REJECTED = 'rejected';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'peminjaman_aset_bmn_id',
        'diterima_by',
        'tanggal_kembali',
        'catatan_pengembalian',
        'bukti_foto_path',
        'bukti_foto_original_name',
        'source',
        'public_nama',
        'public_no_wa',
        'public_email',
        'public_email_verified_at',
        'public_wa_verified_at',
        'public_email_otp',
        'public_wa_otp',
        'public_otp_expires_at',
        'public_verified_at',
        'public_token',
        'public_otp_attempts',
        'verification_status',
        'admin_verified_by',
        'admin_verified_at',
        'admin_verification_note',
        'admin_status',
    ];

    protected $casts = [
        'tanggal_kembali' => 'date',
        'public_email_verified_at' => 'datetime',
        'public_wa_verified_at' => 'datetime',
        'public_otp_expires_at' => 'datetime',
        'public_verified_at' => 'datetime',
        'admin_verified_at' => 'datetime',
    ];

    public function peminjaman(): BelongsTo
    {
        return $this->belongsTo(PeminjamanAsetBmn::class, 'peminjaman_aset_bmn_id');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_by');
    }

    public function adminVerifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_verified_by');
    }

    public function isPublicSource(): bool
    {
        return ($this->source ?? self::SOURCE_LOGIN) === self::SOURCE_PUBLIC;
    }

    public function contactName(): string
    {
        if ($this->isPublicSource()) {
            return (string) ($this->public_nama ?? '-');
        }

        return (string) optional(optional($this->peminjaman)->user)->name ?: '-';
    }

    public function contactEmail(): string
    {
        if ($this->isPublicSource()) {
            return (string) ($this->public_email ?? '-');
        }

        return (string) optional(optional($this->peminjaman)->user)->email ?: '-';
    }

    public function contactWa(): string
    {
        if ($this->isPublicSource()) {
            return (string) ($this->public_no_wa ?? '-');
        }

        return '-';
    }

    public function verificationStatusLabel(): string
    {
        $status = (string) ($this->verification_status ?? 'not_required');

        return match ($status) {
            'pending' => 'Pending',
            'verified' => 'Verified',
            'failed' => 'Failed',
            default => '—',
        };
    }

    public function sourceLabel(): string
    {
        return $this->isPublicSource() ? 'Public' : 'Login';
    }

    public function isPendingAdminVerification(): bool
    {
        return $this->isPublicSource()
            && ($this->verification_status ?? '') === 'verified'
            && ($this->admin_status ?? '') === self::ADMIN_STATUS_PENDING;
    }
}

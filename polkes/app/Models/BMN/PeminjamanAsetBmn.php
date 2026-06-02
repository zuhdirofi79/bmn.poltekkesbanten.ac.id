<?php

declare(strict_types=1);

namespace App\Models\BMN;

use App\Services\BMN\BmnWaNumberNormalizer;
use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Header transaksi peminjaman aset BMN.
 *
 * Status flow:
 *   pending → approved → dipinjam → dikembalikan
 *   pending → rejected
 *
 * @property int $id
 * @property string $kode_peminjaman
 * @property int $user_id
 * @property string|null $jurusan
 * @property string $tujuan_peminjaman
 * @property \Illuminate\Support\Carbon $tanggal_pinjam
 * @property \Illuminate\Support\Carbon $rencana_kembali
 * @property string $status
 * @property int|null $approved_by
 * @property \Illuminate\Support\Carbon|null $approved_at
 * @property int|null $rejected_by
 * @property \Illuminate\Support\Carbon|null $rejected_at
 * @property string|null $catatan
 * @property string|null $nota_dinas_path
 * @property string|null $nota_dinas_original_name
 */
class PeminjamanAsetBmn extends Model
{
    protected $table = 'peminjaman_aset_bmn';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DIPINJAM = 'dipinjam';
    public const STATUS_DIKEMBALIKAN = 'dikembalikan';

    public const APPROVAL_STAGE_VERIFIKATOR_1 = 'verifikator_1';
    public const APPROVAL_STAGE_VERIFIKATOR_2 = 'verifikator_2';
    public const APPROVAL_STAGE_FINAL_REPORT = 'final_report';
    public const APPROVAL_STAGE_COMPLETED = 'completed';
    public const APPROVAL_STAGE_REJECTED = 'rejected';
    public const APPROVAL_STAGE_SCREENING_RUMAH_TANGGA = 'screening_rumah_tangga';
    public const APPROVAL_STAGE_FINAL_HENDRI = 'final_hendri';

    public const SCREENING_PENDING = 'pending';
    public const SCREENING_TERSEDIA = 'tersedia';
    public const SCREENING_TIDAK_TERSEDIA = 'tidak_tersedia';
    public const SCREENING_PERLU_KONFIRMASI = 'perlu_konfirmasi';

    public const KONDISI_FOTO_DIR = 'uploads/peminjaman/kondisi';

    public const SOURCE_LOGIN = 'login';

    public const SOURCE_PUBLIC = 'public';

    public const VERIFICATION_NOT_REQUIRED = 'not_required';

    public const VERIFICATION_PENDING = 'pending';

    public const VERIFICATION_VERIFIED = 'verified';

    public const VERIFICATION_FAILED = 'failed';

    /**
     * Sub-direktori publik (relatif terhadap public/) tempat file nota dinas
     * BARU disimpan. Lokasi ini SENGAJA berada di document root, BUKAN di
     * storage/app/public, agar tidak bergantung pada `php artisan storage:link`
     * — penting pada shared hosting (mis. Hostinger) yang sering tidak
     * mengizinkan symlink.
     */
    public const NOTA_DINAS_DIR = 'uploads/nota_dinas';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kode_peminjaman',
        'user_id',
        'jurusan',
        'tujuan_peminjaman',
        'requested_barang',
        'tanggal_pinjam',
        'rencana_kembali',
        'status',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'catatan',
        'nota_dinas_path',
        'nota_dinas_original_name',
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
        'approval_stage',
        'verifier_1_id',
        'verifier_1_approved_at',
        'verifier_1_note',
        'verifier_2_id',
        'verifier_2_approved_at',
        'verifier_2_note',
        'final_report_by',
        'final_report_at',
        'final_report_note',
        'rejected_stage',
        'rejected_reason',
        'screened_by',
        'screened_at',
        'screening_status',
        'screening_note',
        'kondisi_foto_path',
        'kondisi_foto_original_name',
    ];

    protected $casts = [
        'tanggal_pinjam' => 'date',
        'rencana_kembali' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'public_email_verified_at' => 'datetime',
        'public_wa_verified_at' => 'datetime',
        'public_otp_expires_at' => 'datetime',
        'public_verified_at' => 'datetime',
        'verifier_1_approved_at' => 'datetime',
        'verifier_2_approved_at' => 'datetime',
        'final_report_at' => 'datetime',
        'screened_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function rejecter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejected_by');
    }

    public function verifier1(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_1_id');
    }

    public function verifier2(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verifier_2_id');
    }

    public function finalReporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'final_report_by');
    }

    public function screener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'screened_by');
    }

    public function details(): HasMany
    {
        return $this->hasMany(PeminjamanAsetBmnDetail::class, 'peminjaman_aset_bmn_id');
    }

    public function pengembalian(): HasOne
    {
        return $this->hasOne(PengembalianAsetBmn::class, 'peminjaman_aset_bmn_id');
    }

    /**
     * Apakah transaksi masih membuat aset dianggap "terpakai" (lock).
     * Aset dianggap occupied ketika header bukan rejected dan belum dikembalikan.
     */
    public function holdsAsetLock(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_APPROVED,
            self::STATUS_DIPINJAM,
        ], true);
    }

    public function isPublicSource(): bool
    {
        return ($this->source ?? self::SOURCE_LOGIN) === self::SOURCE_PUBLIC;
    }

    public function getNamaPeminjamAttribute(): string
    {
        return $this->isPublicSource()
            ? ($this->public_nama ?: '-')
            : (optional($this->user)->name ?: '-');
    }

    public function getEmailPeminjamAttribute(): string
    {
        return $this->isPublicSource()
            ? ($this->public_email ?: '-')
            : (optional($this->user)->email ?: '-');
    }

    public function getWaPeminjamAttribute(): string
    {
        return $this->isPublicSource()
            ? ($this->public_no_wa ?: '-')
            : (optional($this->user)->no_hp ?: '-');
    }

    public function getWaPeminjamLinkAttribute(): ?string
    {
        $wa = $this->wa_peminjam;

        if (! $wa || $wa === '-') {
            return null;
        }

        $normalized = BmnWaNumberNormalizer::normalize($wa);

        return $normalized !== '' ? 'https://wa.me/'.$normalized : null;
    }

    public function contactName(): string
    {
        return $this->nama_peminjam;
    }

    public function contactEmail(): string
    {
        return $this->email_peminjam;
    }

    public function contactWa(): string
    {
        return $this->wa_peminjam;
    }

    public function verificationStatusLabel(): string
    {
        $status = (string) ($this->verification_status ?? self::VERIFICATION_NOT_REQUIRED);

        return match ($status) {
            self::VERIFICATION_PENDING => 'Pending',
            self::VERIFICATION_VERIFIED => 'Verified',
            self::VERIFICATION_FAILED => 'Failed',
            default => '—',
        };
    }

    public function sourceLabel(): string
    {
        return $this->isPublicSource() ? 'Public' : 'Login';
    }

    /**
     * @return array{badge: string, label: string}
     */
    public function approvalStageBadge(): array
    {
        $stage = (string) ($this->approval_stage ?? self::APPROVAL_STAGE_VERIFIKATOR_1);

        return match ($stage) {
            self::APPROVAL_STAGE_VERIFIKATOR_1 => ['badge' => 'bg-warning text-dark', 'label' => 'Menunggu Pak Hendri'],
            self::APPROVAL_STAGE_VERIFIKATOR_2 => ['badge' => 'bg-info text-dark', 'label' => 'Menunggu Pak Singgih'],
            self::APPROVAL_STAGE_FINAL_REPORT => ['badge' => 'bg-primary', 'label' => 'Final Report Pak Hendri'],
            self::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA => ['badge' => 'bg-warning text-dark', 'label' => 'Screening Rumah Tangga'],
            self::APPROVAL_STAGE_FINAL_HENDRI => ['badge' => 'bg-primary', 'label' => 'Menunggu Persetujuan Pak Hendri'],
            self::APPROVAL_STAGE_COMPLETED => ['badge' => 'bg-success', 'label' => 'Verifikasi Selesai'],
            self::APPROVAL_STAGE_REJECTED => ['badge' => 'bg-danger', 'label' => 'Ditolak'],
            default => ['badge' => 'bg-secondary', 'label' => ucfirst(str_replace('_', ' ', $stage))],
        };
    }

    public function usesMultiStageApproval(): bool
    {
        return in_array((string) ($this->approval_stage ?? ''), [
            self::APPROVAL_STAGE_VERIFIKATOR_1,
            self::APPROVAL_STAGE_VERIFIKATOR_2,
            self::APPROVAL_STAGE_FINAL_REPORT,
            self::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA,
            self::APPROVAL_STAGE_FINAL_HENDRI,
        ], true);
    }

    public function usesScreeningFlow(): bool
    {
        return in_array((string) ($this->approval_stage ?? ''), [
            self::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA,
            self::APPROVAL_STAGE_FINAL_HENDRI,
        ], true) || ($this->requested_barang !== null && trim((string) $this->requested_barang) !== '');
    }

    public function isAwaitingScreening(): bool
    {
        return $this->status === self::STATUS_PENDING
            && $this->approval_stage === self::APPROVAL_STAGE_SCREENING_RUMAH_TANGGA;
    }

    public function kondisiFotoUrl(): ?string
    {
        $path = trim((string) ($this->kondisi_foto_path ?? ''));
        if ($path === '') {
            return null;
        }

        return asset(ltrim($path, '/'));
    }

    /**
     * @return array{status: string, at: \Illuminate\Support\Carbon|null, note: string|null}
     */
    public function timelineStage1(): array
    {
        if ($this->approval_stage === self::APPROVAL_STAGE_REJECTED
            && $this->rejected_stage === self::APPROVAL_STAGE_VERIFIKATOR_1) {
            return [
                'status' => 'rejected',
                'at' => $this->rejected_at,
                'note' => $this->rejected_reason,
            ];
        }

        if ($this->verifier_1_approved_at !== null) {
            return [
                'status' => 'approved',
                'at' => $this->verifier_1_approved_at,
                'note' => $this->verifier_1_note,
            ];
        }

        return ['status' => 'pending', 'at' => null, 'note' => null];
    }

    /**
     * @return array{status: string, at: \Illuminate\Support\Carbon|null, note: string|null}
     */
    public function timelineStage2(): array
    {
        if ($this->approval_stage === self::APPROVAL_STAGE_REJECTED
            && $this->rejected_stage === self::APPROVAL_STAGE_VERIFIKATOR_2) {
            return [
                'status' => 'rejected',
                'at' => $this->rejected_at,
                'note' => $this->rejected_reason,
            ];
        }

        if ($this->verifier_2_approved_at !== null) {
            return [
                'status' => 'approved',
                'at' => $this->verifier_2_approved_at,
                'note' => $this->verifier_2_note,
            ];
        }

        if ($this->verifier_1_approved_at === null) {
            return ['status' => 'pending', 'at' => null, 'note' => null];
        }

        return ['status' => 'pending', 'at' => null, 'note' => null];
    }

    /**
     * @return array{status: string, at: \Illuminate\Support\Carbon|null, note: string|null}
     */
    public function timelineFinalReport(): array
    {
        if ($this->final_report_at !== null) {
            return [
                'status' => 'completed',
                'at' => $this->final_report_at,
                'note' => $this->final_report_note,
            ];
        }

        if ($this->verifier_2_approved_at === null) {
            return ['status' => 'pending', 'at' => null, 'note' => null];
        }

        return ['status' => 'pending', 'at' => null, 'note' => null];
    }

    /**
     * URL publik untuk file nota dinas, kompatibel mundur dengan path lama.
     *
     * Aturan resolve (urut prioritas):
     *   1. URL absolut (http/https)               → dikembalikan apa adanya
     *   2. Path baru "uploads/nota_dinas/<file>"  → bmn_nota_dinas_url(file)
     *      (memakai env BMN_NOTA_DINAS_BASE_URL bila di-set — penting di
     *       cPanel/Hostinger di mana docroot ≠ Laravel public/)
     *   3. Path lama "storage/<...>"              → asset(path)
     *   4. Path lama "bmn/nota-dinas/<file>"      → asset('storage/'.path)
     *   5. Fallback                                → asset('storage/'.path)
     *
     * @return string|null URL siap pakai, atau null jika kolom kosong.
     */
    public function notaDinasUrl(): ?string
    {
        $path = trim((string) ($this->nota_dinas_path ?? ''));
        if ($path === '') {
            return null;
        }

        // URL absolut, jangan ditimpa.
        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $normalized = ltrim($path, '/');

        if (str_starts_with($normalized, self::NOTA_DINAS_DIR.'/')) {
            // Ambil HANYA basename. URL base sudah berisi "/uploads/nota_dinas",
            // jadi menggabungkan path penuh akan menghasilkan duplikasi segmen.
            return bmn_nota_dinas_url(basename($normalized));
        }

        if (str_starts_with($normalized, 'storage/')) {
            return asset($normalized);
        }

        if (str_starts_with($normalized, 'bmn/nota-dinas/')) {
            return asset('storage/'.$normalized);
        }

        return asset('storage/'.$normalized);
    }

    /**
     * Apakah file nota dinas benar-benar ada di disk. Berlaku untuk upload
     * baru di <docroot>/uploads/nota_dinas/ (path absolut diresolusi via
     * bmn_nota_dinas_path()).
     *
     * Untuk path lama yang mengarah ke storage symlink, TIDAK bisa
     * memvalidasi secara murah tanpa storage:link aktif → return null
     * (= "tidak diketahui", view jangan tampilkan badge "tidak ditemukan").
     *
     * @return bool|null true=ada, false=hilang, null=tidak bisa dicek
     */
    public function notaDinasExistsLocally(): ?bool
    {
        $path = trim((string) ($this->nota_dinas_path ?? ''));
        if ($path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');

        if (str_starts_with($normalized, self::NOTA_DINAS_DIR.'/')) {
            return is_file(bmn_nota_dinas_path(basename($normalized)));
        }

        // Path lama (storage/ / bmn/nota-dinas/) — tidak bisa diverifikasi
        // tanpa storage:link; jangan tampilkan badge "file tidak ditemukan".
        return null;
    }

    /**
     * @param  Builder<PeminjamanAsetBmn>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<PeminjamanAsetBmn>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['approval_stage'])) {
            $query->where('approval_stage', (string) $filters['approval_stage']);
        }

        if (! empty($filters['user_id'])) {
            $query->where('user_id', (int) $filters['user_id']);
        }

        if (! empty($filters['jurusan'])) {
            $query->where('jurusan', (string) $filters['jurusan']);
        }

        if (! empty($filters['tanggal_from'])) {
            $query->whereDate('tanggal_pinjam', '>=', (string) $filters['tanggal_from']);
        }

        if (! empty($filters['tanggal_to'])) {
            $query->whereDate('tanggal_pinjam', '<=', (string) $filters['tanggal_to']);
        }

        if (! empty($filters['q'])) {
            $term = '%'.((string) $filters['q']).'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('kode_peminjaman', 'like', $term)
                    ->orWhere('tujuan_peminjaman', 'like', $term)
                    ->orWhere('public_nama', 'like', $term)
                    ->orWhere('public_email', 'like', $term)
                    ->orWhere('public_no_wa', 'like', $term)
                    ->orWhere('requested_barang', 'like', $term)
                    ->orWhereHas('user', function (Builder $userQuery) use ($term): void {
                        $userQuery->where('name', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('no_hp', 'like', $term);
                    });
            });
        }

        return $query;
    }
}

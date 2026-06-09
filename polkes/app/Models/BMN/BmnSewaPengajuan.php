<?php

declare(strict_types=1);

namespace App\Models\BMN;

use App\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BmnSewaPengajuan extends Model
{
    protected $table = 'bmn_sewa_pengajuan';

    public const JENIS_UMUM = 'umum';

    public const JENIS_PEGAWAI = 'pegawai';

    public const PAYMENT_PENDING = 'pending';

    public const PAYMENT_CONFIRMED = 'confirmed';

    public const PAYMENT_REJECTED = 'rejected';

    public const STATUS_PENDING = 'pending';

    public const STATUS_PAYMENT_REVIEW = 'payment_review';

    public const STATUS_PAYMENT_CONFIRMED = 'payment_confirmed';

    public const STATUS_COORDINATED = 'coordinated';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_COMPLETED = 'completed';

    public const COORD_PENDING = 'pending';

    public const COORD_SENT_TO_TEAM = 'sent_to_team';

    public const COORD_DONE = 'done';

    public const SURAT_DIR = 'uploads/nota_dinas';

    public const BUKTI_BAYAR_DIR = 'uploads/bukti_bayar_sewa';

    /** Path legacy sebelum migrasi folder upload. */
    public const LEGACY_SURAT_DIR = 'uploads/sewa/surat';

    public const LEGACY_BUKTI_BAYAR_DIR = 'uploads/sewa/bukti-bayar';

    /** Status verifikasi email publik (kolom verification_status). */
    public const VERIFICATION_PENDING = 'pending';

    public const VERIFICATION_VERIFIED = 'verified';

    public const VERIFICATION_FAILED = 'failed';

    public const VERIFICATION_NOT_REQUIRED = 'not_required';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kode_sewa',
        'jenis_penyewa',
        'nama_lengkap',
        'nomor_wa',
        'email',
        'tarif_id',
        'tanggal_mulai_sewa',
        'tanggal_rencana_kembali',
        'jumlah_hari_sewa',
        'total_biaya',
        'tujuan_peminjaman',
        'surat_resmi_path',
        'surat_resmi_original_name',
        'bukti_bayar_path',
        'bukti_bayar_original_name',
        'catatan',
        'payment_status',
        'admin_payment_by',
        'admin_payment_at',
        'admin_payment_note',
        'payment_forwarded_by',
        'payment_forwarded_at',
        'coordination_status',
        'coordination_by',
        'coordination_at',
        'coordination_note',
        'public_token',
        'status',
        // === Verifikasi email publik (WA OTP sementara dinonaktifkan) ===
        'public_email_otp',
        'public_wa_otp',
        'public_otp_expires_at',
        'public_otp_attempts',
        'public_email_verified_at',
        'public_wa_verified_at',
        'public_verified_at',
        'verification_status',
    ];

    protected $casts = [
        'tanggal_mulai_sewa' => 'date',
        'tanggal_rencana_kembali' => 'date',
        'jumlah_hari_sewa' => 'integer',
        'total_biaya' => 'decimal:2',
        'admin_payment_at' => 'datetime',
        'payment_forwarded_at' => 'datetime',
        'coordination_at' => 'datetime',
        'public_otp_expires_at' => 'datetime',
        'public_otp_attempts' => 'integer',
        'public_email_verified_at' => 'datetime',
        'public_wa_verified_at' => 'datetime',
        'public_verified_at' => 'datetime',
    ];

    public function tarif(): BelongsTo
    {
        return $this->belongsTo(BmnSewaTarif::class, 'tarif_id');
    }

    public function adminPaymentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_payment_by');
    }

    public function paymentForwardedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_forwarded_by');
    }

    public function coordinationBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'coordination_by');
    }

    public function suratResmiUrl(): ?string
    {
        $path = trim((string) ($this->surat_resmi_path ?? ''));
        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($normalized, self::SURAT_DIR.'/')) {
            return bmn_nota_dinas_url(basename($normalized));
        }

        if (str_starts_with($normalized, self::LEGACY_SURAT_DIR.'/')) {
            return asset($normalized);
        }

        return asset($normalized);
    }

    public function buktiBayarUrl(): ?string
    {
        $path = trim((string) ($this->bukti_bayar_path ?? ''));
        if ($path === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $normalized = ltrim(str_replace('\\', '/', $path), '/');

        if (str_starts_with($normalized, self::BUKTI_BAYAR_DIR.'/')) {
            return bmn_bukti_bayar_sewa_url(basename($normalized));
        }

        if (str_starts_with($normalized, self::LEGACY_BUKTI_BAYAR_DIR.'/')) {
            return asset($normalized);
        }

        return asset($normalized);
    }

    public function suratResmiOriginalName(): string
    {
        $name = trim((string) ($this->surat_resmi_original_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $path = trim((string) ($this->surat_resmi_path ?? ''));

        return $path !== '' ? basename($path) : 'Surat Resmi';
    }

    public function buktiBayarOriginalName(): string
    {
        $name = trim((string) ($this->bukti_bayar_original_name ?? ''));
        if ($name !== '') {
            return $name;
        }

        $path = trim((string) ($this->bukti_bayar_path ?? ''));

        return $path !== '' ? basename($path) : 'Bukti Bayar';
    }

    public function jenisPenyewaLabel(): string
    {
        return $this->jenis_penyewa === self::JENIS_PEGAWAI ? 'Pegawai' : 'Umum';
    }

    /**
     * Apakah pengajuan sewa ini sudah lolos verifikasi email pemohon.
     * Memakai kolom `public_email_verified_at` bila ada, dengan fallback
     * ke `verification_status === 'verified'` (kompat data lama).
     */
    public function isEmailVerified(): bool
    {
        if ($this->public_email_verified_at !== null) {
            return true;
        }

        return (string) ($this->verification_status ?? '') === self::VERIFICATION_VERIFIED;
    }

    public function verificationStatusLabel(): string
    {
        $status = (string) ($this->verification_status ?? self::VERIFICATION_PENDING);

        return match ($status) {
            self::VERIFICATION_PENDING => 'Menunggu Verifikasi Email',
            self::VERIFICATION_VERIFIED => 'Email Terverifikasi',
            self::VERIFICATION_FAILED => 'Verifikasi Gagal',
            self::VERIFICATION_NOT_REQUIRED => 'Tidak Perlu Verifikasi',
            default => 'Menunggu Verifikasi Email',
        };
    }

    /**
     * Tarif per satuan (per hari) yang berlaku untuk pengajuan ini berdasar
     * jenis_penyewa. Mengembalikan null jika tarif sudah tidak ada.
     */
    public function tarifDipakai(): ?float
    {
        $tarif = $this->tarif;
        if ($tarif === null) {
            return null;
        }

        if ($this->jenis_penyewa === self::JENIS_PEGAWAI && $tarif->tarif_pegawai !== null) {
            return (float) $tarif->tarif_pegawai;
        }

        return (float) $tarif->tarif_umum;
    }

    /**
     * Jumlah hari sewa inklusif (mulai s/d kembali termasuk hari mulai).
     * Mengutamakan kolom tersimpan; fallback hitung dari tanggal.
     */
    public function jumlahHariDihitung(): int
    {
        $stored = (int) ($this->jumlah_hari_sewa ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        if ($this->tanggal_mulai_sewa === null || $this->tanggal_rencana_kembali === null) {
            return 0;
        }

        $diff = (int) $this->tanggal_mulai_sewa->diffInDays($this->tanggal_rencana_kembali, false);

        return $diff >= 0 ? $diff + 1 : 0;
    }

    /**
     * Total biaya sewa. Mengutamakan kolom tersimpan; fallback hitung dari
     * tarifDipakai * jumlahHari.
     */
    public function totalBiayaDihitung(): ?float
    {
        if ($this->total_biaya !== null && (float) $this->total_biaya > 0) {
            return (float) $this->total_biaya;
        }

        $tarif = $this->tarifDipakai();
        $hari = $this->jumlahHariDihitung();
        if ($tarif === null || $hari <= 0) {
            return null;
        }

        return $tarif * $hari;
    }

    public function paymentStatusLabel(): string
    {
        return match ($this->payment_status) {
            self::PAYMENT_CONFIRMED => 'Terbayar',
            self::PAYMENT_REJECTED => 'Pembayaran Ditolak',
            default => 'Menunggu Verifikasi',
        };
    }

    public function coordinationStatusLabel(): string
    {
        return match ($this->coordination_status) {
            self::COORD_SENT_TO_TEAM => 'Dikoordinasi Tim',
            self::COORD_DONE => 'Koordinasi Selesai',
            default => 'Belum Dikoordinasi',
        };
    }

    /**
     * Pesan publik berdasarkan kombinasi payment_status (untuk halaman cek status).
     */
    public function publicMessage(): string
    {
        $waRetno = User::SEWA_ADMIN_RETNO_WA_DISPLAY;

        return match ($this->payment_status) {
            self::PAYMENT_CONFIRMED => 'Pembayaran sudah terkonfirmasi. Bu Retno/Pak Singgih akan melakukan koordinasi menuju hari H.',
            self::PAYMENT_REJECTED => 'Pembayaran belum dapat dikonfirmasi. Silakan hubungi Bu Retno '.$waRetno.'.',
            default => 'Pembayaran sedang menunggu verifikasi Bu Indah.',
        };
    }

    /**
     * Generate token publik 40 char untuk akses cek status terbatas
     * (dipakai sebagai cross-check tambahan ke email/no WA).
     */
    public static function generatePublicToken(): string
    {
        return Str::lower(Str::random(40));
    }

    /**
     * Scope filter untuk halaman daftar admin sewa.
     *
     * @param  Builder<self>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<self>
     */
    public function scopeSewaFilter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
        if (! empty($filters['payment_status'])) {
            $query->where('payment_status', (string) $filters['payment_status']);
        }
        if (! empty($filters['jenis_penyewa'])) {
            $query->where('jenis_penyewa', (string) $filters['jenis_penyewa']);
        }
        if (! empty($filters['bulan'])) {
            // format: YYYY-MM
            $parts = explode('-', (string) $filters['bulan']);
            if (count($parts) === 2 && checkdate((int) $parts[1], 1, (int) $parts[0])) {
                $query->whereYear('created_at', (int) $parts[0])
                    ->whereMonth('created_at', (int) $parts[1]);
            }
        }
        if (! empty($filters['tahun']) && ! empty($filters['bulan_angka'])) {
            $tahun = (int) $filters['tahun'];
            $bulan = (int) $filters['bulan_angka'];
            if ($bulan >= 1 && $bulan <= 12) {
                $query->whereYear('created_at', $tahun)->whereMonth('created_at', $bulan);
            }
        }
        if (! empty($filters['q'])) {
            $q = '%'.trim((string) $filters['q']).'%';
            $query->where(function (Builder $sub) use ($q): void {
                $sub->where('kode_sewa', 'like', $q)
                    ->orWhere('nama_lengkap', 'like', $q)
                    ->orWhere('nomor_wa', 'like', $q)
                    ->orWhere('email', 'like', $q);
            });
        }

        return $query;
    }
}

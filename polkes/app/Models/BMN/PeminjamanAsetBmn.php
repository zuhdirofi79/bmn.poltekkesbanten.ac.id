<?php

declare(strict_types=1);

namespace App\Models\BMN;

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
    ];

    protected $casts = [
        'tanggal_pinjam' => 'date',
        'rencana_kembali' => 'date',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
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
                    ->orWhere('tujuan_peminjaman', 'like', $term);
            });
        }

        return $query;
    }
}

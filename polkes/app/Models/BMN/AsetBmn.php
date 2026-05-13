<?php

declare(strict_types=1);

namespace App\Models\BMN;

use App\Models\BmnSatker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AsetBmn extends Model
{
    use SoftDeletes;

    protected $table = 'aset_bmn';

    /**
     * Mass assignment: lokasi penyimpanan utama = kolom teks `lokasi` (alamat bebas).
     * Kolom `lokasi_id` tidak di-assign lewat form CRUD; diatur terpisah (mis. mutasi).
     *
     * @var list<string>
     */
    protected $fillable = [
        'kode_barang',
        'nama_barang',
        'kategori_id',
        'nilai_perolehan',
        'tanggal_perolehan',
        'kondisi',
        'status',
        'nup',
        'nomor_register',
        'asset_tag',
        'asset_tag_hash',
        'asset_tag_type',
        'merk',
        'foto',
        'foto_url',
        'foto_preview_url',
        'foto_provider',
        'pengguna_ruangan',
        'satker_id',
        'satker',
        'lokasi',
        'lokasi_id',
        'harga_satuan',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    /**
     * @var list<string>
     */
    protected $dates = ['deleted_at'];

    protected $casts = [
        'nilai_perolehan' => 'decimal:2',
        'tanggal_perolehan' => 'date',
        'foto' => 'array',
    ];

    public function kategori(): BelongsTo
    {
        return $this->belongsTo(KategoriAset::class, 'kategori_id');
    }

    /**
     * Relasi ke master lokasi (legacy / mutasi). Teks tampil gunakan atribut `lokasi` bila tersedia.
     */
    public function lokasiMaster(): BelongsTo
    {
        return $this->belongsTo(LokasiAset::class, 'lokasi_id');
    }

    public function satkerRel(): BelongsTo
    {
        return $this->belongsTo(BmnSatker::class, 'satker_id');
    }

    public function mutasi(): HasMany
    {
        return $this->hasMany(MutasiAset::class, 'aset_id')->orderByDesc('tanggal_mutasi');
    }

    public function histori(): HasMany
    {
        return $this->hasMany(HistoriAset::class, 'aset_id')->orderByDesc('created_at');
    }

    /**
     * Single-source-of-truth filter untuk daftar aset.
     *
     * Mendukung pencarian global (q) lintas kolom utama + pencarian per kolom.
     *
     * @param  Builder<AsetBmn>  $query
     * @param  array<string, mixed>  $filters
     * @return Builder<AsetBmn>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        $globalSearchColumns = [
            'kode_barang',
            'nama_barang',
            'nup',
            'lokasi',
            'satker',
            'pengguna_ruangan',
            'merk',
        ];

        if (! empty($filters['q'])) {
            $term = '%'.((string) $filters['q']).'%';
            $query->where(function (Builder $q) use ($term, $globalSearchColumns): void {
                foreach ($globalSearchColumns as $col) {
                    $q->orWhere($col, 'like', $term);
                }
            });
        }

        $likeColumns = [
            'kode_barang',
            'nup',
            'nama_barang',
            'merk',
            'pengguna_ruangan',
            'satker',
        ];

        foreach ($likeColumns as $col) {
            if (! empty($filters[$col])) {
                $query->where($col, 'like', '%'.((string) $filters[$col]).'%');
            }
        }

        if (! empty($filters['lokasi'])) {
            $term = '%'.((string) $filters['lokasi']).'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('lokasi', 'like', $term)
                    ->orWhereHas('lokasiMaster', function (Builder $q2) use ($term): void {
                        $q2->where('nama_lokasi', 'like', $term);
                    });
            });
        }

        if (! empty($filters['kategori_id'])) {
            $query->where('kategori_id', (int) $filters['kategori_id']);
        }

        if (! empty($filters['satker_id'])) {
            $query->where('satker_id', (int) $filters['satker_id']);
        }

        if (! empty($filters['kondisi'])) {
            $query->where('kondisi', (string) $filters['kondisi']);
        }

        if (! empty($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }

        if (! empty($filters['tahun_perolehan'])) {
            $query->whereYear('tanggal_perolehan', (int) $filters['tahun_perolehan']);
        }

        if (! empty($filters['tanggal_from'])) {
            $query->whereDate('tanggal_perolehan', '>=', (string) $filters['tanggal_from']);
        }

        if (! empty($filters['tanggal_to'])) {
            $query->whereDate('tanggal_perolehan', '<=', (string) $filters['tanggal_to']);
        }

        if (isset($filters['nilai_min']) && $filters['nilai_min'] !== '' && $filters['nilai_min'] !== null) {
            $query->where('nilai_perolehan', '>=', (float) $filters['nilai_min']);
        }

        if (isset($filters['nilai_max']) && $filters['nilai_max'] !== '' && $filters['nilai_max'] !== null) {
            $query->where('nilai_perolehan', '<=', (float) $filters['nilai_max']);
        }

        if (! empty($filters['created_from'])) {
            $query->whereDate('created_at', '>=', (string) $filters['created_from']);
        }

        if (! empty($filters['created_to'])) {
            $query->whereDate('created_at', '<=', (string) $filters['created_to']);
        }

        return $query;
    }

    public function labelLokasi(): string
    {
        if ($this->lokasi !== null && $this->lokasi !== '') {
            return (string) $this->lokasi;
        }

        return (string) (optional($this->lokasiMaster)->nama_lokasi ?? '-');
    }
}

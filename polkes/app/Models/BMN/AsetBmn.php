<?php

declare(strict_types=1);

namespace App\Models\BMN;

use App\Models\BmnSatker;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AsetBmn extends Model
{
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
        'kondisi',
        'tanggal_perolehan',
        'nilai_perolehan',
        'lokasi',
        'pengguna_ruangan',
        'satker_id',
        'satker',
        'status',
        'nup',
        'nomor_register',
        'asset_tag',
        'asset_tag_hash',
        'asset_tag_type',
        'foto',
        'foto_url',
        'foto_preview_url',
        'foto_provider',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'nilai_perolehan' => 'decimal:2',
        'tanggal_perolehan' => 'date',
        'deleted_at' => 'datetime',
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
     * @param  Builder<AsetBmn>  $query
     * @return Builder<AsetBmn>
     */
    public function scopeFilter(Builder $query, array $filters): Builder
    {
        if (! empty($filters['lokasi'])) {
            $w = (string) $filters['lokasi'];
            $term = '%'.$w.'%';
            $query->where(function (Builder $q) use ($term): void {
                $q->where('lokasi', 'like', $term)
                    ->orWhereHas('lokasiMaster', function (Builder $q2) use ($term): void {
                        $q2->where('nama_lokasi', 'like', $term);
                    });
            });
        }
        if (! empty($filters['kondisi'])) {
            $query->where('kondisi', $filters['kondisi']);
        }
        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
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

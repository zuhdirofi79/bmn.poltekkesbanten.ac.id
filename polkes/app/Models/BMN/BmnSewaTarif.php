<?php

declare(strict_types=1);

namespace App\Models\BMN;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class BmnSewaTarif extends Model
{
    protected $table = 'bmn_sewa_tarifs';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'kategori',
        'nama_layanan',
        'satuan',
        'tarif_umum',
        'tarif_pegawai',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'tarif_umum' => 'decimal:2',
        'tarif_pegawai' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function pengajuan(): HasMany
    {
        return $this->hasMany(BmnSewaPengajuan::class, 'tarif_id');
    }

    public function photos(): HasMany
    {
        return $this->hasMany(BmnSewaTarifFoto::class, 'tarif_id')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function primaryPhoto(): HasOne
    {
        return $this->hasOne(BmnSewaTarifFoto::class, 'tarif_id')
            ->where('is_primary', 1)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function tarifForJenis(string $jenisPenyewa): float
    {
        if ($jenisPenyewa === 'pegawai' && $this->tarif_pegawai !== null) {
            return (float) $this->tarif_pegawai;
        }

        return (float) $this->tarif_umum;
    }
}

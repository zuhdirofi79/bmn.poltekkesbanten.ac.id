<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PersediaanRak extends Model
{
    public const STATUS_AKTIF = 'aktif';
    public const STATUS_NONAKTIF = 'nonaktif';

    protected $table = 'persediaan_rak';

    protected $fillable = [
        'kode_rak',
        'nama_rak',
        'unit_kerja',
        'lokasi_detail',
        'qr_token',
        'qr_path',
        'status',
        'created_by',
        'updated_by',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(PersediaanRakBarang::class, 'rak_id');
    }

    public function barangs()
    {
        return $this->belongsToMany(
            PersediaanBarang::class,
            'persediaan_rak_barang',
            'rak_id',
            'barang_id',
        )->withTimestamps();
    }

    public function isAktif(): bool
    {
        return $this->status === self::STATUS_AKTIF;
    }
}

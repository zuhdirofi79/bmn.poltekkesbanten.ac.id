<?php

declare(strict_types=1);

namespace App\Models\BMN;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LokasiAset extends Model
{
    protected $table = 'lokasi_aset';

    protected $fillable = [
        'nama_lokasi',
        'deskripsi',
    ];

    public function aset(): HasMany
    {
        return $this->hasMany(AsetBmn::class, 'lokasi_id');
    }
}

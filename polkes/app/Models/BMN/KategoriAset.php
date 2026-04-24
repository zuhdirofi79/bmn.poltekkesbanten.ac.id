<?php

declare(strict_types=1);

namespace App\Models\BMN;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class KategoriAset extends Model
{
    protected $table = 'kategori_aset';

    protected $fillable = [
        'nama_kategori',
    ];

    public function aset(): HasMany
    {
        return $this->hasMany(AsetBmn::class, 'kategori_id');
    }
}

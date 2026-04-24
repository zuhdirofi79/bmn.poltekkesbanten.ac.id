<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\BMN\AsetBmn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BmnSatker extends Model
{
    protected $table = 'bmn_satker';

    public $timestamps = false;

    protected $fillable = [
        'kode',
        'nama',
        'slug',
        'sort_order',
    ];

    public function aset(): HasMany
    {
        return $this->hasMany(AsetBmn::class, 'satker_id');
    }
}

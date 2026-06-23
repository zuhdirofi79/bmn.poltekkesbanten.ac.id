<?php

declare(strict_types=1);

namespace App\Models\BhpKebersihan;

use Illuminate\Database\Eloquent\Model;

class BhpKebersihanStockOpname extends Model
{
    protected $table = 'bhp_kebersihan_stock_opname';

    protected $fillable = [
        'tanggal_opname',
        'catatan',
        'created_by',
    ];

    protected $casts = [
        'tanggal_opname' => 'date',
    ];

    public function details()
    {
        return $this->hasMany(BhpKebersihanStockOpnameDetail::class, 'opname_id');
    }
}

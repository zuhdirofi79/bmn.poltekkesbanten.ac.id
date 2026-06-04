<?php

declare(strict_types=1);

namespace App\Models\Persediaan;

use Illuminate\Database\Eloquent\Model;

class PersediaanStockOpname extends Model
{
    protected $table = 'persediaan_stock_opname';

    protected $fillable = [
        'unit_kerja',
        'tanggal_opname',
        'created_by',
        'catatan',
    ];

    protected $casts = [
        'tanggal_opname' => 'date',
    ];

    public function items()
    {
        return $this->hasMany(PersediaanStockOpnameItem::class, 'opname_id');
    }
}

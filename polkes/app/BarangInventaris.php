<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BarangInventaris extends Model
{
    protected $table = 'barang_inventaris';
    protected $guarded = [];
    public $timestamps = FALSE;

    public function location()
    {
        return $this->belongsTo(LabLocation::class, 'location_id');
    }
}

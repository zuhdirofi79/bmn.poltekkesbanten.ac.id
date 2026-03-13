<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Ruangan extends Model
{
    protected $table = 'ruangan';
    protected $guarded = [];
    public $timestamps = FALSE;

    /**
     * Location is owned by the room, not the asset.
     * Access: $barang->ruangan->location->name
     */
    public function location()
    {
        return $this->belongsTo(LabLocation::class, 'location_id');
    }

    public function barangInventaris()
    {
        return $this->hasMany(BarangInventaris::class, 'ruangan_id');
    }
}

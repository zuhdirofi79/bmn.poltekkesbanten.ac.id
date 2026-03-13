<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BarangInventaris extends Model
{
    protected $table = 'barang_inventaris';
    protected $guarded = [];
    public $timestamps = FALSE;

    /**
     * Location always comes through the room:
     *   $item->ruangan->location->name
     *
     * The column barang_inventaris.location_id still exists in the DB
     * for backward compatibility but is no longer used in application code.
     */
    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }
}

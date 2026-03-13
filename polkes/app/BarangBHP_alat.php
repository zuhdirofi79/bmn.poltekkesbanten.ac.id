<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BarangBHP_alat extends Model
{
    protected $table = 'barangbhp_alat';
    protected $guarded = [];
    public $timestamps = FALSE;

    /**
     * Prepared for when ruangan_id is added to barangbhp_alat table.
     * Until then, $item->ruangan returns null — blade uses optional().
     */
    public function ruangan()
    {
        return $this->belongsTo(Ruangan::class, 'ruangan_id');
    }
}

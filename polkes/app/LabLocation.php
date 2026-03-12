<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class LabLocation extends Model
{
    protected $table = 'lab_locations';
    protected $guarded = [];

    public function barangInventaris()
    {
        return $this->hasMany(BarangInventaris::class, 'location_id');
    }
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PeminjamanDetail extends Model
{
    protected $table = "peminjaman_detail";
    protected $guarded = [];
    public $timestamps = FALSE;

    public function peminjaman()
    {
        return $this->belongsTo(Peminjaman::class, 'peminjaman_id');
    }
}
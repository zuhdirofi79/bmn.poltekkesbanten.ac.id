<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Peminjaman extends Model
{
    protected $table = "peminjaman";
    protected $guarded = [];
    public $timestamps = FALSE;

    public function prodi()
    {
        return $this->belongsTo(\App\Models\Prodi::class, 'prodi_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\User::class, 'user_id');
    }

    public function getJamMulaiAttribute()
    {
        return $this->waktu_mulai ?? null;
    }

    public function getJamSelesaiAttribute()
    {
        return $this->waktu_selesai ?? null;
    }
}
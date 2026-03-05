<?php

namespace App;
use Illuminate\Database\Eloquent\Model;

class BarangInventaris extends Model
{
    protected $table = "barang_inventaris";
    protected $guarded = [];
    public $timestamps = FALSE;
}

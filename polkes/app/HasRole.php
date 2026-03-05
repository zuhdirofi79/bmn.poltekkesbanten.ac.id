<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class HasRole extends Model
{
    protected $table = 'model_has_roles';
    protected $fillable = [
        'role_id','model_type','model_id'
    ];
    public $timestamps = FALSE;
}

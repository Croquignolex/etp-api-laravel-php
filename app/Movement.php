<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Movement extends Model
{
    protected $table = 'movements';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'name', 'type', 'in', 'out', 'balance', 'id_user', 'manager');
    protected $visible = array('name', 'type', 'in', 'out', 'balance', 'created_at', 'manager');
}


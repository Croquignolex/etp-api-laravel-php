<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    protected $table = 'transactions';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'name', 'type', 'in', 'out', 'operator', 'balance', 'left', 'right', 'id_manager');
    protected $visible = array('name', 'type', 'in', 'out', 'operator', 'left', 'right', 'balance', 'created_at');
}


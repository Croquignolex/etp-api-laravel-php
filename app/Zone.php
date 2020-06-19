<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Zone extends Model
{
    protected $table = 'zones';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'nom', 'reference', 'description');
    protected $visible = array('id', 'nom', 'reference', 'description', 'created_at');

}


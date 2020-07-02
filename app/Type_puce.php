<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Type_puce extends Model
{
    protected $table = 'type_puces';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'name', 'description');
    protected $visible = array('id', 'name', 'description', 'created_at');
}

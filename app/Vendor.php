<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vendor extends Model
{
    protected $table = 'vendors';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'name', 'solde', 'description');
    protected $visible = array('id', 'name', 'solde', 'description', 'created_at');
}


<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agency extends Model
{
    protected $table = 'agencies';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'name', 'id_manager', 'description');
    protected $visible = array('id', 'name', 'id_manager', 'description', 'created_at');

    public function manager()
    {
        return $this->belongsTo('App\User', 'id_manager');
    }

    public function puces()
    {
        return $this->hasMany('App\Puce', 'id_agency');
    }
}


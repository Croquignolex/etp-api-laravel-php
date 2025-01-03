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
    protected $fillable = array('id', 'nom', 'id_responsable', 'map', 'reference', 'description');
    protected $visible = array('id', 'nom', 'id_responsable', 'map', 'reference', 'description', 'created_at');
	 
    public function users() {
        return $this->hasMany('App\User', 'id_zone');
    }

    public function responsable()
    {
        return $this->belongsTo('App\User', 'id_responsable');
    }
}


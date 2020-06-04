<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Caisse extends Model 
{

    protected $table = 'caisses';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('nom', 'description', 'reference');
    protected $visible = array('nom', 'description', 'reference');

    public function versements()
    {
        return $this->hasMany('App\Versement', 'id_caisse');
    }

}
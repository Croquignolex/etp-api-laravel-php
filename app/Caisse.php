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
    protected $fillable = array('nom', 'description', 'id_user', 'solde', 'reference');
    protected $visible = array('id', 'nom', 'description', 'id_user', 'solde', 'reference', 'created_at');

    public function versements()
    {
        return $this->hasMany('App\Versement', 'id_caisse');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }

}

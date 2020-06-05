<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Agent extends Model 
{

    protected $table = 'agents';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('nom', 'id_creator', 'id_user', 'img_cni', 'phone', 'reference', 'adresse', 'taux_commission', 'email', 'pays');
    protected $visible = array('nom','id_user', 'id_creator', 'img_cni', 'phone', 'reference', 'adresse', 'taux_commission', 'email', 'pays');

    public function vercements()
    {
        return $this->hasMany('App\Versement', 'id_agent');
    }

}
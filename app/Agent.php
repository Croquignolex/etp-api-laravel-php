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
    protected $fillable = array('img_cni','img_cni_back', 'point_de_vente', 'reference', 'taux_commission', 'ville', 'pays', 'id_creator', 'id_user');
    protected $visible = array('id', 'created_at', 'img_cni','img_cni_back', 'point_de_vente', 'reference', 'taux_commission', 'ville', 'pays', 'id_creator', 'id_user');

    public function vercements()
    {
        return $this->hasMany('App\Versement', 'id_agent');
    }

    public function Puce()
    {
        return $this->hasMany('App\Transaction', 'id_agent');
    }

}

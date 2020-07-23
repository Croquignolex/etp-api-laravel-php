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
    protected $fillable = array('img_cni', 'dossier', 'img_cni_back', 'point_de_vente', 'reference', 'taux_commission', 'ville', 'pays', 'id_creator', 'id_user');
    protected $visible = array('id', 'dossier', 'created_at', 'img_cni','img_cni_back', 'point_de_vente', 'reference', 'taux_commission', 'ville', 'pays', 'id_creator', 'id_user');


    public function puces()
    {
        return $this->hasMany('App\Puce', 'id_agent');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }

}

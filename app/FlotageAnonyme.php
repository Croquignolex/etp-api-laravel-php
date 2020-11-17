<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class FlotageAnonyme extends Model
{
    protected $table = 'flotage_anonymes';
    public $timestamps = true;
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'id_user', 'nro_sim_to', 'id_sim_from', 'reference', 'statut', 'nom_agent', 'montant');
    protected $visible = array('id', 'id_user', 'nro_sim_to', 'id_sim_from', 'reference', 'statut', 'nom_agent', 'montant', 'created_at');

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }

    public function sim_from()
    {
        return $this->belongsTo('App\Puce', 'id_sim_from');
    }

}

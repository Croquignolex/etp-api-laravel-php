<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Flottage_Rz extends Model
{
    protected $table = 'flottage_rz';
    public $timestamps = true;
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'id_responsable_zone', 'id_agent', 'id_sim_agent', 'reference', 'statut', 'montant', 'reste');
    protected $visible = array('id', 'id_responsable_zone', 'id_agent', 'id_sim_agent', 'reference', 'statut', 'montant', 'reste', 'created_at');

    public function responsable_zone()
    {
        return $this->belongsTo('App\User', 'id_responsable_zone');
    }

    public function agent()
    {
        return $this->belongsTo('App\Agent', 'id_agent');
    }
}

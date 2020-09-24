<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Flottage_interne extends Model
{

    protected $table = 'flottage_internes';
    public $timestamps = true;
    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'id_user', 'id_sim_from', 'id_sim_to', 'reference', 'statut', 'note', 'montant', 'reste');
    protected $visible = array('id', 'id_user', 'id_sim_from', 'id_sim_to', 'reference', 'statut', 'note', 'montant', 'reste', 'created_at');

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }
}

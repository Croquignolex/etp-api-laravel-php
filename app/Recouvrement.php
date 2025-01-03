<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recouvrement extends Model
{

    protected $table = 'recouvrements';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'id_user', 'id_transaction','id_flottage', 'id_versement', 'type_transaction','reference', 'montant', 'reste', 'recu', 'statut', 'user_destination', 'user_source');
    protected $visible = array('id', 'id_user', 'id_transaction', 'id_flottage', 'id_versement', 'type_transaction','reference', 'montant', 'reste', 'recu', 'statut', 'user_destination', 'user_source', 'created_at');

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }

    public function source_user()
    {
        return $this->belongsTo('App\User', 'user_source');
    }

    public function flottage()
    {
        return $this->belongsTo('App\Approvisionnement', 'id_flottage');
    }
}

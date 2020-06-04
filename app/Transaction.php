<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model 
{

    protected $table = 'transactions';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id_user', 'id_versement', 'id_type_transaction', 'id_flote', 'montant', 'reste', 'statut', 'user_destination', 'user_source');
    protected $visible = array('id_user', 'id_versement', 'id_type_transaction', 'id_flote', 'montant', 'reste', 'statut', 'user_destination', 'user_source');

    public function commission()
    {
        return $this->hasOne('App\Commission', 'id_transaction');
    }

    public function vercements()
    {
        return $this->hasMany('App\Versement_transaction', 'id_transaction');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }

    public function type_transaction()
    {
        return $this->belongsTo('App\Type_transaction', 'id_type_transaction');
    }

    public function flote()
    {
        return $this->belongsTo('App\Flote', 'id_flote');
    }

}
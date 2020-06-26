<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Demande_destockage extends Model
{
    protected $table = 'demande_destockages';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id_user', 'id_puce', 'reference', 'montant','statut', 'user_source', 'user_destination');
    protected $visible = array('id_user', 'id_puce', 'reference', 'montant','statut', 'user_source', 'user_destination');


    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }

    public function puce()
    {
        return $this->belongsTo('App\Puce', 'id_puce');
    }
}


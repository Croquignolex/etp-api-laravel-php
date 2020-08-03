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
    protected $fillable = array('id','id_user', 'add_by', 'reference', 'montant', 'reste','statut', 'puce_source', 'puce_destination');
    protected $visible = array('id','id_user', 'add_by', 'reference', 'montant','statut', 'reste', 'puce_source', 'puce_destination', 'created_at');


    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }

    public function puce()
    {
        return $this->belongsTo('App\Puce', 'puce_destination');
    }

    //les destockages enregistrées pour une demande precise
    public function destockages()
    {
        return $this->hasMany('App\Destockage', 'id_demande_destockage');
    }
}


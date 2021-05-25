<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Demande_flote extends Model
{
    protected $table = 'demande_flotes';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];

    protected $fillable = array('id','id_user', 'reference', 'add_by','reste', 'montant', 'statut', 'source', 'id_puce');
    protected $visible = array('id','id_user', 'reference', 'add_by','reste', 'montant', 'statut', 'source', 'id_puce', 'created_at');

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }

    public function creator()
    {
        return $this->belongsTo('App\User', 'add_by');
    }

    public function puce()
    {
        return $this->belongsTo('App\Puce', 'id_puce');
    }

    //les approvisionnement enregistrées pour une demande precise
    public function approvisionnements()
    {
        return $this->hasMany('App\Approvisionnement', 'id_demande_flote');
    }
}


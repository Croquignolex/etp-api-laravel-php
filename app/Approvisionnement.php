<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Approvisionnement extends Model 
{

    protected $table = 'approvisionnements';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id_demande_flote', 'id_user', 'reference', 'statut', 'note', 'montant');
    protected $visible = array('id', 'id_demande_flote', 'id_user', 'reference', 'statut', 'note', 'montant', 'created_at');

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }

    public function demande_flote()
    {
        return $this->belongsTo('App\Demande_flote', 'id_demande_flote');
    }
}


<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Destockage extends Model
{
    protected $table = 'destockages';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id_demande_destockage', 'id_user', 'reference', 'statut', 'note', 'montant');
    protected $visible = array('id_demande_destockage', 'id_user', 'reference', 'statut', 'note', 'montant');

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }
}


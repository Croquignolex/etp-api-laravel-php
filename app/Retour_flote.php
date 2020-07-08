<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Retour_flote extends Model
{
    protected $table = 'retour_flotes';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id_user', 'id_approvisionnement', 'reference', 'montant','reste', 'statut', 'user_destination', 'user_source');
    protected $visible = array('id_user', 'id_approvisionnement', 'reference', 'montant','reste', 'statut', 'user_destination', 'user_source');
                                                     

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }
}
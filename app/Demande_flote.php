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
    protected $fillable = array('id_user', 'reference', 'add_by', 'montant', 'statut', 'source', 'id_puce');
    protected $visible = array('id_user', 'reference', 'add_by', 'montant', 'statut', 'source', 'id_puce');
                                                     

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }

    public function puce()
    {
        return $this->belongsTo('App\Puce', 'id_puce');
    }
}


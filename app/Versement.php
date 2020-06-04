<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Versement extends Model 
{

    protected $table = 'versements';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id_caisse', 'id_agent', 'id_flote', 'montant', 'note', 'reste_sur_versement');
    protected $visible = array('id_caisse', 'id_agent', 'id_flote', 'montant', 'note', 'reste_sur_versement');

    public function transactions()
    {
        return $this->hasMany('Versement_transaction', 'id_versement');
    }

    public function agent()
    {
        return $this->belongsTo('App\Agent', 'id_agent');
    }

    public function operation()
    {
        return $this->hasOne('App\Operation', 'id_versement');
    }

    public function caisse()
    {
        return $this->belongsTo('App\Caisse', 'id_caisse');
    }

}
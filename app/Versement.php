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
    protected $fillable = array('id_caisse', 'correspondant', 'add_by', 'montant', 'note', 'recu', 'statut');
    protected $visible = array('id', 'id_caisse', 'correspondant', 'add_by', 'montant', 'note', 'recu', 'statut', 'created_at');

    public function caisse()
    {
        return $this->belongsTo('App\Caisse', 'id_caisse');
    }

    public function user()
    {
        return $this->belongsTo('App\User', 'add_by');
    }

    public function related()
    {
        return $this->belongsTo('App\User', 'correspondant');
    }
}

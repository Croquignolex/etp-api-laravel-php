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
    protected $fillable = array('id_caisse', 'correspondant', 'add_by', 'montant', 'note', 'recu');
    protected $visible = array('id_caisse', 'correspondant', 'add_by', 'montant', 'note', 'recu');

    public function caisse()
    {
        return $this->belongsTo('App\Caisse', 'id_caisse');
    }

}
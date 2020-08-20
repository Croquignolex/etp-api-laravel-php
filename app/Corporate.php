<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Corporate extends Model
{

    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('nom', 'phone', 'responsable','dossier', 'adresse', 'numeros_agents', 'description');
    protected $visible = array('nom', 'phone', 'responsable','dossier', 'adresse', 'numeros_agents', 'description');


    public function puces()
    {
        return $this->hasMany('App\Puce', 'corporate');
    }
}

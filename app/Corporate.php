<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Corporate extends Model
{

    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'nom', 'phone', 'responsable','dossier', 'adresse', 'numeros_agents', 'description');
    protected $visible = array('id', 'nom', 'phone', 'responsable','dossier', 'adresse', 'numeros_agents', 'description', 'created_at');


    public function puces()
    {
        return $this->hasMany('App\Puce', 'corporate');
    }
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Puce extends Model
{
    protected $table = 'puces';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'nom', 'id_flotte', 'id_agent', 'reference', 'numero', 'solde', 'description');
    protected $visible = array('id', 'nom', 'id_flotte', 'id_agent', 'numero', 'reference', 'solde', 'description', 'created_at');

    public function transactions()
    {
        return $this->hasMany('App\Transaction', 'id_puce');
    }


    public function flote()
    {
        return $this->belongsTo('App\Flote', 'id_flotte');
    }

    public function agent()
    {
        return $this->belongsTo('App\Agent', 'id_agent');
    }

    
}
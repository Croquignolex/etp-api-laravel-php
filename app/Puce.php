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

    protected $fillable = array('id', 'nom', 'id_flotte', 'corporate', 'id_agent', 'reference', 'numero', 'type', 'id_rz', 'solde', 'description');
    protected $visible = array('id', 'nom', 'id_flotte', 'id_agent', 'corporate', 'numero', 'reference', 'type', 'id_rz', 'description', 'created_at');

    public function flote()
    {
        return $this->belongsTo('App\Flote', 'id_flotte');
    }

    public function agent()
    {
        return $this->belongsTo('App\Agent', 'id_agent');
    }

    public function rz()
    {
        return $this->belongsTo('App\User', 'id_rz');
    }

	public function type_puce()
    {
        return $this->belongsTo('App\Type_puce', 'type');
    }

    public function company()
    {
        return $this->belongsTo('App\Corporate', 'corporate');
    }

}

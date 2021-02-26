<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Liquidite extends Model
{
    protected $table = 'liquidites';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id_user', 'id_reception', 'montant', 'note', 'statut');
    protected $visible = array('id', 'id_user', 'id_reception', 'montant', 'note', 'statut', 'created_at');

    public function reception()
    {
        return $this->belongsTo('App\User', 'id_reception');
    }

    public function emission()
    {
        return $this->belongsTo('App\User', 'id_user');
    }
}

<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Motif_operation extends Model 
{

    protected $table = 'motif_operations';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('nom', 'description');
    protected $visible = array('nom', 'description');

    public function operations()
    {
        return $this->hasMany('App\Operation', 'id_motif');
    }

}
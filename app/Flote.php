<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Flote extends Model 
{

    protected $table = 'flotes';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'nom', 'description');
    protected $visible = array('id', 'nom', 'description', 'created_at');

    public function Puce()
    {
        return $this->hasMany('App\Transaction', 'id_flote');
    }

}
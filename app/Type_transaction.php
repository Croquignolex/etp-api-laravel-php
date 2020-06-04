<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Type_transaction extends Model 
{

    protected $table = 'type_transactions';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('nom', 'description');
    protected $visible = array('nom', 'description');

    public function transactions()
    {
        return $this->hasMany('App\Transaction', 'id_type_transaction');
    }

}
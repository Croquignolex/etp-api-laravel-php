<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Versement_transaction extends Model 
{

    protected $table = 'versement_transactions';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id_transaction', 'id_versement');
    protected $visible = array('id_transaction', 'id_versement');

    public function transaction()
    {
        return $this->belongsTo('App\Transaction', 'id_transaction');
    }

    public function vercement()
    {
        return $this->belongsTo('App\Versement', 'id_versement');
    }

}
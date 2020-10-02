<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Commission extends Model 
{

    protected $table = 'commissions';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id_transaction', 'montant', 'statut');
    protected $visible = array('id_transaction', 'montant', 'statut');

    public function transaction()
    {
        return $this->belongsTo('App\Approvisionnement', 'id_transaction');
    }

}
<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Operation extends Model
{

    protected $table = 'operations';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id','id_versement', 'id_motif', 'id_user', 'description', 'flux', 'montant');
    protected $visible = array('id','id_versement', 'id_motif', 'id_user', 'description', 'flux', 'montant');

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }

    public function motif_operation()
    {
        return $this->belongsTo('App\Motif_operation', 'id_motif');
    }

    public function versement()
    {
        return $this->belongsTo('App\Versement', 'id_versement');
    }

}

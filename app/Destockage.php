<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Destockage extends Model
{
    protected $table = 'destockages';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id_recouvreur','type','id_puce','id_agent','fournisseur','recu', 'reference', 'statut', 'note', 'montant');
    protected $visible = array('id_recouvreur','type','id_puce','id_agent','fournisseur','recu', 'reference', 'statut', 'note', 'montant','created_at');

    public function user()
    {
        return $this->belongsTo('App\User', 'add_by');
    }

    public function agent()
    {
        return $this->belongsTo('App\Agent', 'id_agent');
    }

    public function puce()
    {
        return $this->belongsTo('App\Puce', 'id_puce');
    }

    public function demande_destockage()
    {
        return $this->belongsTo('App\Demande_destockage', 'id_demande_destockage');
    }
}


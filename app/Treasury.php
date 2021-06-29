<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Treasury extends Model
{
    protected $table = 'treasuries';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'name', 'amount', 'reason', 'type', 'receipt', 'id_manager', 'id_vendor', 'description');
    protected $visible = array('id', 'name', 'amount', 'reason', 'type', 'receipt', 'description', 'created_at');

    public function manager()
    {
        return $this->belongsTo('App\User', 'id_manager');
    }

    public function vendor()
    {
        return $this->belongsTo('App\Vendor', 'id_vendor');
    }
}


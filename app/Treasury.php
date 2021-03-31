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
    protected $fillable = array('id', 'name', 'amount', 'reason', 'type', 'receipt', 'id_manager', 'description');
    protected $visible = array('id', 'name', 'amount', 'reason', 'type', 'receipt', 'description', 'created_at');

    public function manager()
    {
        return $this->belongsTo('App\User', 'id_manager');
    }
}


<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Transaction extends Model
{
    protected $table = 'transactions';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'name', 'type', 'in', 'out', 'id_operator', 'balance', 'left', 'right', 'id_left', 'id_right', 'id_user');
    protected $visible = array('name', 'type', 'in', 'out', 'id_operator', 'left', 'right', 'balance', 'id_left', 'id_right', 'created_at');

    public function user()
    {
        return $this->belongsTo('App\User', 'id_user');
    }

    public function operator()
    {
        return $this->belongsTo('App\Flote', 'id_operator');
    }

    public function right_sim()
    {
        return $this->belongsTo('App\Puce', 'id_right');
    }

    public function left_sim()
    {
        return $this->belongsTo('App\Puce', 'id_left');
    }
}


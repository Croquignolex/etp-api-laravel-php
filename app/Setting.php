<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Setting extends Model
{
    protected $table = 'settings';
    public $timestamps = true;

    use SoftDeletes;

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'cards', 'charts', 'bars', 'sound', 'session', 'id_user', 'description');
    protected $visible = array('id', 'cards', 'charts', 'bars', 'sound', 'session', 'description', 'created_at');
	 
    public function user() {
        return $this->belongsTo('App\User', 'id_user');
    }
}


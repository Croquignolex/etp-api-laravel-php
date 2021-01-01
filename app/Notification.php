<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $dates = ['deleted_at'];
    protected $fillable = array('id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at');
    protected $visible = array('id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at');
}

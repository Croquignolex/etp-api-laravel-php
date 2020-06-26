<?php

namespace App;

use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Laravel\Passport\HasApiTokens;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;



class User extends Authenticatable
{
    protected $table = 'users';
    public $timestamps = true;
    

    use SoftDeletes;
	use Notifiable;
    use HasRoles;
	use HasApiTokens;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name','avatar', 'email','add_by', 'id_zone', 'poste', 'statut', 'password', 'phone', 'adresse', 'description',
    ];
	
	protected $dates = ['deleted_at'];
    protected $visible = array('id','name','add_by','id_zone', 'created_at', 'poste', 'statut','avatar', 'password', 'phone', 'adresse', 'description', 'email');

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
	

    public function operations()
    {
        return $this->hasMany('App\Operation', 'id_user');
    }

    public function AauthAcessToken(){
        return $this->hasMany('\App\OauthAccessToken');
    }
	
	   
}

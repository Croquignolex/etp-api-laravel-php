<?php

namespace App;

use Laravel\Passport\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;

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
        'name','avatar', 'email','add_by', 'id_zone', 'id_agency', 'poste', 'statut',
        'password', 'phone', 'adresse', 'description', 'dette'
    ];

	protected $dates = ['deleted_at'];
    protected $visible = array(
        'id','name','add_by','id_zone', 'id_agency', 'created_at', 'poste',
        'statut','avatar', 'password', 'phone', 'adresse', 'description', 'email', 'dette'
    );

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

    // Createur des utilisateurs
    public function creator()
    {
        return $this->belongsTo('App\User', 'add_by');
    }

    //les opérations enregistrées par un utilisateur precis
    public function operations() {
        return $this->hasMany('App\Operation', 'id_user');
    }

    //les puce d'un responsable de zone
    public function puces()
    {
        return $this->hasMany('App\Puce', 'id_rz');
    }

    //les demande de destockages enregistrées pour un utilisateur precis
    public function demande_destockages() {
        return $this->hasMany('App\Demande_destockage', 'id_user');
    }

    //les demande de flotes enregistrées pour un utilisateur precis
    public function demande_flotes() {
        return $this->hasMany('App\Demande_flote', 'id_user');
    }

	public function setting() {
        return $this->hasMany('App\Setting', 'id_user');
    }

	public function zone() {
        return $this->belongsTo('App\Zone', 'id_zone');
    }

    public function agency() {
        return $this->belongsTo('App\Agency', 'id_agency');
    }

    public function AauthAcessToken(){
        return $this->hasMany('\App\OauthAccessToken');
    }

    public function agent()
    {
        return $this->hasMany('App\Agent', 'id_user');
    }

    public function caisse()
    {
        return $this->hasMany('App\Caisse', 'id_user');
    }

    public function flottage_rz()
    {
        return $this->hasMany('App\Flottage_Rz', 'id_responsable_zone');
    }

    public static function boot()
    {
        parent::boot();
        static::deleting(function($user)
        {
            //on supprime ses puces
            $user->agent()->first()->puces()->delete();

            //on supprime l'agent associé
            $user->agent()->delete();

            //on supprime sa caisse
            $user->caisse()->delete();
        });
    }
}

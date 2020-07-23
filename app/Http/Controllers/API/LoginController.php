<?php

namespace App\Http\Controllers\API;

use App\User;
use Illuminate\Http\Request;
use App\Enums\Statut;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Zone;
use App\Agent; 
use App\Utiles\ImageFromBase64;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Storage;

class LoginController extends Controller
{
    /**
     * Connection d'un utilisateur
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        // Valider données envoyées
        $rules = [
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['required', 'integer']
        ];

        $input = ['password' => $request->password, 'phone' => $request->phone];

        if(!Validator::make($input, $rules)->passes()){

            $val = Validator::make($input, $rules);

            return response()->json(
                [
                    'message' => 'Un ou plusieurs valeurs du formulaire incorrect',
                    'status' => false,
                    'data' => null
                ]
            );
        }

		// on verifie que l'utilisateur n'est ni Archivé ni desactivé
			$userAnable = User::where('phone', $request->phone)->first();

			if ($userAnable != null) {

				if ($userAnable->deleted_at != null) {
					return response()->json(
						[
							'message' => 'Cet utilisateur est archivé',
							'status' => false,
							'data' => null,
						]
					);
				} 
				
				if ($userAnable->statut == Statut::DECLINE) {
					return response()->json(
						[
							'message' => 'Cet utilisateur est archivé',
							'status' => false,
							'data' => null,
						]
					);
				} 
				
			}


		$credentials = [
			'phone' => $request->phone,
			'password' => $request->password
		];

        //si la connexion est bonne
        if (auth()->attempt($credentials)) {
            // Créer un token pour l'utilisateur
            $token = auth()->user()->createToken(config('app.name', 'ETP'));

			$user = auth()->user();
			 
			// recuperer l'agent et ses puces associé à l'utilisateur (utile pour l'agent)
			$agent = Agent::where('id_user', $user->id)->first();
			$puces = is_null($agent) ? [] : $agent->puces;

            // Définir quand le token va s'expirer
            /*$token->token->expires_at = Carbon::now()->addHour();

            $token->token->save();*/

            return response()->json(
                [
                    'message' => null,
                    'status' => true,
                    'data' => [
                        'token' =>$token->accessToken,
						'zone' => $user->zone,
                        'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
						'role' => $user->roles->first(),
						'agent' => $agent,
						'puces' => $puces,
						'setting' => $user->setting->first()
                    ]
                ]
            );
        } else {
            return response()->json(
                [
                    'message' => "Combinaison login et mot de passe incorrect",
                    'status' => false,
                    'data' => null,
                ]
            );
        }
    }

    /**
     * si un utilisateur n'est pas connecté
     *
     * @return JsonResponse
     */
    public function not_login()
    {
        return response()->json(
            [
                'message' => 'vous n etes pas connecté',
                'status' => false,
                'data' => null
            ]
        );
    }

    /**
     * deconnexion d'un utilisateur
     *
     * @return JsonResponse
     */
    public function logout()
    {
        if (Auth::check()) {
            Auth::user()->AauthAcessToken()->delete();
            return response()->json(
                [
                    'message' => 'utilisateur deconnecté',
                    'status' => true,
                    'data' => null
                ]
            );
        }else{
            return response()->json(
                [
                    'message' => 'impossible de se deconnecter si on n est pas connecte',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * details d'un utilisateur connecté
     *
     * @return JsonResponse
     */
    public function details()
    {
        if (Auth::check()) {
            $user = Auth::user();
            $userRole = $user->roles->pluck('name','name')->all();
            $zones = json_decode($user->id_zone);
            $zones_list = [];
            
            if ($zones != null) {
                foreach ($zones as $zone) {
                    $zones_list[] = Zone::Find($zone);
                }
            }
            

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'user' => $user->setHidden(['deleted_at']),
                        'zone' => Zone::Find($user->id_zone),
                        'userRole' => $userRole,
                        'zones_list' => $zones_list
                    ]
                ]
            );
         }else{
            return response()->json(
                [
                    'message' => 'impossible de trouver l utilisateur connecté',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    } 

    /**
     * Réinitialisation du mot de passe
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function reset(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'current_pass' => 'required|string',
            'new_pass' => 'required|string|min:6',
        ]);
        if ($validator->fails()) { 

            return response()->json(
                [
                    'message' => ['error'=>$validator->errors()],
                    'status' => false,
                    'data' => null
                ]
            );
                       
        }

        // Récupérer l'utilisateur concerné
        $user = Auth::user();

        if (!Hash::check($request->current_pass, $user->password)) {

            // Mot de passe courant incorrect
            return response()->json(
                [
                    'message' => 'Mot de passe courant incorrect',
                    'status' => false,
                    'data' => null
                ]
            );
        }

        $pass_data = array(
            'current_pass' => $request->current_pass,
            'new_pass' => $request->new_pass,
        );


        // crypter le nouveau mot de passe
        $pass_data['new_pass'] = bcrypt($pass_data['new_pass']);        

        // Changer le mot de passe de l'utilisateur
        $user->password = $pass_data['new_pass'];

        if ($user->save()) {
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Mot de passe réinitialisé avec succès',
                    'status' => true,
                    'data' => ['user'=>$user]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'Echec de réinitialisation du mot de passe',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * @param Base64ImageRequest $request
     * @return JsonResponse
     */
    public function update_picture(Request $request)
    {

        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'base_64_image' => 'required|string', 
        ]);
        
        if ($validator->fails()) { 

            return response()->json(
                [
                    'message' => ['error'=>$validator->errors()],
                    'status' => false,
                    'data' => null
                ]
            );
                       
        }
        
        // Get current user
        $user =  Auth::user();
        $user_avatar_path_name =  $user->avatar;

        //Delete old file before storing new file
        if(Storage::exists($user_avatar_path_name) && $user_avatar_path_name != 'users/default.png')
            Storage::delete($user_avatar_path_name);


        // Convert base 64 image to normal image for the server and the data base
        $server_image_name_path = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image'),
            'images/avatars/');

        // Changer l' avatar de l'utilisateur
        $user->avatar = $server_image_name_path;

        // Save image name in database      
        if ($user->save()) {
            return response()->json(
                [
                    'message' => 'Photo de profil mise à jour avec succès',
                    'status' => true,
                    'data' => ['user'=>$user]
                ]
            );
        }else {
            return response()->json(
                [
                    'message' => 'erreur de modification de l avatar',
                    'status' => true,
                    'data' => ['user'=>$user]
                ]
            );
        }
        
    }
	
	/**
     * @param Base64ImageRequest $request
     * @return JsonResponse
     */
    public function update_setting(Request $request)
    { 
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'bars' => 'array', 
            'cards' => 'array', 
            'charts' => 'array', 
            'sound' => 'required',  
            'session' => 'required', 
            //'description' => 'string', 
        ]);
        
        if ($validator->fails()) {  
            return response()->json(
                [
                    'message' => ['error'=>$validator->errors()],
                    'status' => false,
                    'data' => null
                ]
            );
                       
        }
        
        // Get current user 
		$setting = Auth::user()->setting->first();
		$setting->sound = $request->sound;
		$setting->session = $request->session;
		$setting->description = $request->description;
		$setting->bars = json_encode($request->bars);
		$setting->charts = json_encode($request->charts);
		$setting->cards = json_encode($request->cards);
        // Save image name in database      
        if ($setting->save()) {
            return response()->json(
                [
                    'message' => 'Setting upadated',
                    'status' => true,
                    'data' => null
                ]
            );
        }else {
            return response()->json(
                [
                    'message' => 'erreur de modification de l avatar',
                    'status' => true,
                    'data' => []
                ]
            );
        }
        
    }

	/** 
     * modification d'un utilisateur 
     */ 
    public function edit_profile(Request $request) 
    {   
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            // 'statut' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'poste' => ['nullable', 'string', 'max:255'],
            // 'email' => ['required', 'string', 'email'],
            'adresse' => ['nullable', 'string', 'max:255'],
            // 'roles' => ['required'],
            // 'phone' => ['required', 'numeric', 'max:255']

        ]);
        if ($validator->fails()) { 
            return response()->json(
                [
                    'message' => ['error'=>$validator->errors()],
                    'status' => false,
                    'data' => null
                ]
            );            
        }

          
        // Récupérer les données validées
        $name = $request->name;
        $description = $request->description;
        // $email = $request->email;
        $adresse = $request->adresse;
        // $status = $request->status;
        $poste = $request->poste;
        // $phone = $request->phone;

        // Get current user
        $user =  Auth::user();
        $user->name = $name;
        // $user->statut = $status;
        // $user->phone = $phone;
        $user->poste = $poste;

        $user->description = $description;
        // $user->email = $email;
        $user->adresse = $adresse;

        if ($user->save()) {

            // Renvoyer un message de succès          
            return response()->json(
                [
                    'message' => 'profil modifié',
                    'status' => true,
                    'data' => ['user'=>$user]
                ]
            );
        } else {

            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la modification',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }
}

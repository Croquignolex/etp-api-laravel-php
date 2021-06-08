<?php

namespace App\Http\Controllers\API;

use App\Enums\Roles;
use App\Enums\Statut;
use App\User;
use App\Zone;
use App\Caisse;
use Illuminate\Http\Request;
use App\Utiles\ImageFromBase64;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class LoginController extends Controller
{
    /**
     * Improve user identification
     *
     * @param Request $request
     * @return JsonResponse
     */
     public function identification(Request $request)
     {
         // valider données envoyées
         $rules = ['phone' => ['required', 'string', 'max:9', 'min:9']];

         // credentials
         $credentials = ['phone' => $request->phone];

         if(!Validator::make($credentials, $rules)->passes()) {
             return response()->json([
                 'message' => "Une ou plusieurs valeurs du formulaire incorrect",
                 'status' => false,
                 'data' => null
             ]);
         }

         // Check if user exist into database
         $userEnable = User::where('phone', $request->phone)->where('statut', Statut::APPROUVE)->first();

         // si la connexion est bonne
         if ($userEnable !== null) {
             return response()->json([
                 'message' => null,
                 'status' => true,
                 'data' => null,
             ]);
         } else {
             return response()->json([
                 'message' => "Utilisateur non reconnu",
                 'status' => false,
                 'data' => null,
             ]);
         }
     }

    /**
     * Improve user authentication
     *
     * @return JsonResponse
     */
    public function authentication(Request $request)
    {
        $user = Auth::user();
        $role = $request->role;
        $user_role = $user->roles->first()->name;

        if($user_role === $role) {
            return response()->json([
                'message' => "Bienvenue " . $user->name,
                'status' => true,
                'data' => [
                    'settings' => $user->setting->first(),
                    'user' => $user,
                    'role' => $user_role
                ]
            ]);
        }
        return response()->json([
            'message' => "Utilisateur non authorisé sur ce rôle",
            'status' => false,
            'data' => null,
        ]);
    }

    /**
     * Connection d'un utilisateur
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        // valider données envoyées
        $rules = [
            'password' => ['required', 'string', 'min:6'],
            'phone' => ['required', 'string', 'max:9', 'min:9']
        ];

        // credentials
        $credentials = ['password' => $request->password, 'phone' => $request->phone];

        if(!Validator::make($credentials, $rules)->passes()) {
            return response()->json([
                'message' => "Une ou plusieurs valeurs du formulaire incorrect",
                'status' => false,
                'data' => null
            ]);
        }

        // si la connexion est bonne
        if (auth()->attempt($credentials)) {
            // Créer un token pour l'utilisateur
            $token = auth()->user()->createToken(config('app.name', 'ETP'));

			$user = auth()->user();

			$role = $user->roles->first()->name;

			if($role === Roles::AGENT) {
			    if($user->agent->first()->reference === Roles::RESSOURCE)
			        $role = Roles::RESSOURCE;
            }

            return response()->json([
                'message' => null,
                'status' => true,
                'data' => [
                    'token' => $token->accessToken,
                    'role' => $role,
                ]
            ]);
        } else {
            return response()->json([
                'message' => 'Combinaison du login et mot de passe incorrect',
                'status' => false,
                'data' => null,
            ]);
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
                    'message' => null,
                    'status' => true,
                    'data' => null
                ]
            );
        }else{
            return response()->json(
                [
                    'message' => "impossible de se deconnecter si on n'est pas connecte",
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

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'user' => $user->setHidden(['deleted_at']),
                        'zone' => Zone::Find($user->id_zone),
                        'userRole' => $userRole
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
     * Solde de l'utilisateur connecté
     */
    // RESPONSABLE DE ZONE
    public function solde()
    {
        $user = Auth::user();
        $caisse = Caisse::where('id_user', $user->id)->first();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'balance' => $caisse->solde,
                'dette' => $user->dette,
            ]
        ]);
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
            'current_pass' => ['required', 'string', 'min:6'],
            'new_pass' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                    'message' => "Le formulaire contient des champs mal renseignés",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // Récupérer l'utilisateur concerné
        $user = Auth::user();

        if (!Hash::check($request->current_pass, $user->password)) {
            // Mot de passe courant incorrect
            return response()->json([
                    'message' => "Mot de passe courant incorrect",
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
            return response()->json([
                    'message' => "Mot de passe mis à jour avec succès",
                    'status' => true,
                    'data' => null
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => "Echec de réinitialisation du mot de passe",
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    public function update_picture(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'base_64_image' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                    'message' => "Le formulaire contient des champs mal renseignés",
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
            return response()->json([
                    'message' => "Photo de profil mise à jour avec succès",
                    'status' => true,
                    'data' => null
                ]
            );
        }else {
            return response()->json([
                    'message' => "Erreur lors de la modification de photo de profil",
                    'status' => true,
                    'data' => null
                ]
            );
        }
    }

    /**
     * @param Request $request
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // Get current user
		$setting = Auth::user()->setting->first();
		$setting->sound = $request->sound;
		$setting->session = $request['session'];
		$setting->description = $request->description;
		$setting->bars = json_encode($request->bars);
		$setting->charts = json_encode($request->charts);
		$setting->cards = json_encode($request->cards);
        // Save image name in database
        if ($setting->save()) {
            return response()->json(
                [
                    'message' => 'Paramètres mis à jour avec succès',
                    'status' => true,
                    'data' => null
                ]
            );
        }else {
            return response()->json(
                [
                    'message' => "Erreur l'ors de la mise à jours des paramètres",
                    'status' => true,
                    'data' => null
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
            'email' => ['nullable', 'string', 'email'],
            'name' => ['required', 'string', 'max:255'],
            'poste' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                    'message' => "Le formulaire contient des champs mal renseignés",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // Récupérer les données validées
        $name = $request->name;
        $description = $request->description;
        $email = $request->email;
        $adresse = $request->adresse;
        $poste = $request->poste;

        // Get current user
        $user =  Auth::user();
        $user->name = $name;
        $user->poste = $poste;

        $user->description = $description;
        $user->email = $email;
        $user->adresse = $adresse;

        if ($user->save()) {
            // Renvoyer un message de succès
            return response()->json([
                    'message' => "Informations du profil mis à jour avec succès",
                    'status' => true,
                    'data' => null
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json([
                    'message' => "Erreur lors de la modification",
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }
}

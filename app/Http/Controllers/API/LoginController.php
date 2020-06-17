<?php

namespace App\Http\Controllers\API;

use App\User;
use Illuminate\Http\Request;
use App\Enums\Statut;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

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
                    'message' => ['error'=>$val->errors()],
                    'status' => false,
                    'data' => null
                ]
            );
        }


            // on verifie que l'utilisateur n'est ni Archivé ni desactivé
                $userAnable = User::where('phone', $request->phone)->first();

                if ($userAnable == null) {
                    return response()->json(
                        [
                            'message' => 'cet utilisateur est Archivé',
                            'status' => false,
                            'data' => null
                        ]
                    ); 
                }elseif ($userAnable->statut == Statut::DECLINE) {
                    return response()->json(
                        [
                            'message' => 'cet utilisateur est desactivé',
                            'status' => false,
                            'data' => null
                        ]
                    );                    
                }

                // on verifie que l'utilisateur n'est pas Archivé
                $userAnable = User::where('phone', $request->phone)->first();
                if ($userAnable == null) {
                    return response()->json(
                        [
                            'message' => 'cet utilisateur est Archivé',
                            'status' => false,
                            'data' => null
                        ]
                    ); 
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

            // Définir quand le token va s'expirer
            /*$token->token->expires_at = Carbon::now()->addHour();

            $token->token->save();*/

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'access_token' =>$token->accessToken,
                        'user' => $user->setHidden(['deleted_at'])
                    ]
                ]
            );
        } else {
            return response()->json(
                [
                    'message' => "Login ou mot de passe incorrect!",
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
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'user' => $user->setHidden(['deleted_at']),
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


}
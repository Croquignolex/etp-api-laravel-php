<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 
use App\User; 
use App\Utiles\ImageFromBase64;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\Validator; 
use Illuminate\Support\Facades\Hash;



class UserController extends Controller
{

    /** 
     * Connection d'un utilisateur 
     */ 
    public function login(Request $request){ 

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


            // on verifie que l'utilisateur n'est pas bloqué
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
                        'data' => ['access_token'=>$token->accessToken, 'user' => $user,]
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
     * Inscription d'un utilisateur 
     */ 

    public function register(Request $request) 
    { 
        $validator = Validator::make($request->all(), [ 
            'name' => 'required',
            'phone' => 'required|numeric|unique:users,phone', 
            'adresse' => 'nullable',
            'description' => 'nullable',
            'base_64_image' => 'required|string',
            'email' => 'required|email|unique:users,email', 
            'password' => 'required|string|min:6', 
            'c_password' => 'required|same:password',
            'roles' => 'required', 
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

        
        // on verifie si le role est définit
        $roleExist = Role::where('name', $request->roles)->count();
        if ($roleExist == 0) {
            return response()->json(
                [
                    'message' => 'ce role n est pas défini',
                    'status' => false,
                    'data' => null
                ]
            ); 
        }


        // Convert base 64 image to normal image for the server and the data base
        $server_image_name_path = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image'),
            'images/avatars/');

        $avatar = null;
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $avatar = $request->avatar->store('images/avatars');
        }

        $input = $request->all(); 
            $input['password'] = bcrypt($input['password']); 
            $input['avatar'] = $server_image_name_path;
            $user = User::create($input); 
            $user->assignRole($request->input('roles'));
            $success['token'] =  $user->createToken('MyApp')-> accessToken; 
            $success['user'] =  $user;
            
                
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['user'=>$success]
                ]
            ); 
    }

/** 
     * details d'un utilisateur 
     */ 
    public function details_user($id) 
    { 
        $userCount = User::Where('id', $id)->count();
        if ($userCount != 0) {
            $user = User::find($id);
            $userRole = $user->roles->pluck('name','name')->all();
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['user' => $user, 'userRole' => $userRole]
                ]
            );
         }else{
            return response()->json(
                [
                    'message' => 'impossible de trouver l utilisateur spécifié',
                    'status' => false,
                    'data' => null
                ]
            ); 
         }        
 
    } 

    /** 
     * details d'un utilisateur connecté
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
                    'data' => ['user' => $user, 'userRole' => $userRole]
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
     * si un utilisateur n'est pas connecté 
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
     * liste des utilisateurs
     */ 
    public function list()
    {
        if (Auth::check()) {
            $user = User::where('deleted_at', null)->get();
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['user' => $user]
                ]
            );
         }else{
            return response()->json(
                [
                    'message' => 'Vous n etes pas connecte',
                    'status' => false,
                    'data' => null
                ]
            );
         }
        
    } 

    /** 
     * supprimer un utilisateur
     */ 
    public function delete($id)
    {
        if (Auth::check()) {
            $user = User::find($id);
            $user->deleted_at = now();
            if ($user->save()) {
                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => 'utilisateur archivé',
                        'status' => true,
                        'data' => null
                    ]
                );
            } else {
                // Renvoyer une erreur
                return response()->json(
                    [
                        'message' => 'erreur de suppression',
                        'status' => false,
                        'data' => null
                    ]
                );
            } 
         }else{
            return response()->json(
                [
                    'message' => 'Vous n etes pas connecté',
                    'status' => false,
                    'data' => null
                ]
            ); 
         }
        
    }


    /** 
     * modification d'un utilisateur 
     */ 
    public function edit_user(Request $request, $id) 
    { 

        //voir si l'utilisateur à modifier existe
        if(!User::Find($id)){

            // Renvoyer un message de notification
            return response()->json(
                [
                    'message' => 'utilisateur non trouvé',
                    'status' => false,
                    'data' => null
                ]
            );
            
        }
        
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'name' => ['required', 'string', 'max:255'],
            'statut' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email'],
            'adresse' => ['required', 'string', 'max:255'],
            'roles' => ['required'],
            'phone' => ['required', 'numeric', 'max:255'] 

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

        // on verifie si le role est définit
        $roleExist = Role::where('name', $request->roles)->count();
        if ($roleExist == 0) {
            return response()->json(
                [
                    'message' => 'ce role n est pas défini',
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
        $status = $request->status;
        $phone = $request->phone;

        // Modifier le profil de l'utilisateur
        $user = User::Find($id);
        $user->name = $name;
        $user->statut = $status;
        $user->phone = $phone;

        $user->description = $description;
        $user->email = $email;
        $user->adresse = $adresse;


        if ($user->save()) {

            DB::table('model_has_roles')->where('model_id',$id)->delete();
            $user->assignRole($request->input('roles'));

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


    /**
     * Réinitialisation du mot de passe
     */
    public function reset(Request $request)
    {

        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'phone' => 'required|numeric|unique:users,phone', 
            'password' => 'required|string|min:6', 
            'c_password' => 'required|same:password', 
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



        // Récupérer le numéro de téléphone
        $telephone = $request->phone;

        // Récupérer le mot de passe
        $password = $request->password;

        // Récupérer l'utilisateur concerné
        $user = User::where('phone', $telephone)->first();

        // Changer le mot de passe de l'utilisateur
        $user->password = Hash::make($password);

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

        // Changer le mot de passe de l'utilisateur
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
     * modification d'un utilisateur 
     */ 
    public function edit_role_user(Request $request, $id) 
    { 

        //voir si l'utilisateur à modifier existe
        if(!User::Find($id)){

            // Renvoyer un message de notification
            return response()->json(
                [
                    'message' => 'utilisateur non trouvé',
                    'status' => false,
                    'data' => null
                ]
            );
            
        }
        
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 

            'roles' => ['required']

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

        // on verifie si le role est définit
        $roleExist = Role::where('name', $request->roles)->count();
        if ($roleExist == 0) {
            return response()->json(
                [
                    'message' => 'ce role n est pas défini',
                    'status' => false,
                    'data' => null
                ]
            ); 
        }

 
        DB::table('model_has_roles')->where('model_id',$id)->delete();
        $user = User::Find($id);

        if ($user->assignRole($request->input('roles'))) {            

            // Renvoyer un message de succès          
            return response()->json(
                [
                    'message' => 'Role modifié',
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

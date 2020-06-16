<?php

namespace App\Http\Controllers\API;

use App\User;
use Illuminate\Http\Request;
use App\Utiles\ImageFromBase64;
use App\Enums\Statut;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;


class UserController extends Controller
{


            /***

     * les conditions de lecture des methodes

     */

    function __construct(){

        $this->middleware('permission:Superviseur');

   }
   

    /**
     * Inscription d'un utilisateur
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'phone' => 'required|numeric|unique:users,phone',
            'adresse' => 'nullable',
            'description' => 'nullable',
            'poste' => ['nullable', 'string', 'max:255'],
            //'base_64_image' => 'required|string',
            'email' => 'required|email|unique:users,email', 
            'password' => 'required|string|min:6', 
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
        //$server_image_name_path = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image'),
            //'images/avatars/');

        $input = $request->all(); 
            $input['password'] = bcrypt($input['password']); 
            //$input['avatar'] = $server_image_name_path;
            $input['add_by'] = Auth::user()->id;
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
     * //Approuver ou desapprouver un utilisateur
     */ 
    public function edit_user_status($id) 
    { 
        $user = User::Find($id);
        $user_status = $user->statut;

        if ($user == null) {

            // Renvoyer un message d'erreur          
            return response()->json(
                [
                    'message' => 'lutilisateur introuvable',
                    'status' => true,
                    'data' => null
                ]
            );

        }elseif ($user_status == Statut::DECLINE) {

            // Approuver
            $user->statut = Statut::APPROUVE;

            
        }else{

            // desapprouver
            $user->statut = Statut::DECLINE;
        }
  
         
        if ($user->save()) {

            // Renvoyer un message de succès          
            return response()->json(
                [
                    'message' => 'Statut changé',
                    'status' => true,
                    'data' => ['user'=>$user]
                ]
            );
        } else {

            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la modification du statut',
                    'status' => false,
                    'data' => null
                ]
            );
        }
 
    }      

    

    /**
     * liste des utilisateurs
     *
     * @return JsonResponse
     */
    public function list()
    {
        if (Auth::check()) {
                //$users = User::where('deleted_at', null)->get();


            /*$util = User::join('model_has_roles', 'model_has_roles.model_id', 'users.id')
            ->join('roles', 'roles.id', 'model_has_roles.role_id')
            ->select(
                'users.name', 'users.poste', 'users.email', 'users.phone', 'users.adresse','users.created_at','users.statut','users.avatar', 'roles.name as role'
            )
            ->get();*/

            $users = User::where('deleted_at', null)->get();
            $returenedUers = [];
            foreach($users as $user) {

                $returenedUers[] = ['user' => $user, 'role' => $user->roles->pluck('name','name')->all()];

            }         
                return response()->json(
                    [
                        'message' => '',
                        'status' => true,
                        'data' => ['users' => $returenedUers]
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
     *
     * @param $id
     * @return JsonResponse
     */
    public function delete($id)
    {
        if (Auth::user()->id == $id) {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'impossible de supprimer votre compte',
                    'status' => false,
                    'data' => null
                ]
            );
        }
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

        // Modifier le profil de l'utilisateur
        $user = User::Find($id);
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

            'role' => ['required']

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
        $roleExist = Role::where('name', $request->role)->count();
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

        if ($user->assignRole($request->input('role'))) {            

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

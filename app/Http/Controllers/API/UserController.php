<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller; 
use App\User; 
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth; 
use Illuminate\Support\Facades\Validator; 
use Illuminate\Support\Facades\Hash;



class UserController extends Controller
{

    public $successStatus = 200;

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        //
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }




    /** 
     * Connection d'un utilisateur 
     * 
     * @return \Illuminate\Http\Response 
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
                        'message' => $val->errors(),
                        'success' => 'false',
                        'status' => 400
                    ],
                    400
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

                // on recupère la liste des roles
                $roles = Role::pluck('name','name')->all();

                $user = auth()->user();

                // Définir quand le token va s'expirer
                /*$token->token->expires_at = Carbon::now()->addHour();

                $token->token->save();*/

                return response()->json(
                    [
                        'access_token' => $token->accessToken,
                        'user' => $user,
                        'roles' => $roles,
                        'token_type' => 'Bearer',
                        'status' => 200,
                        'login' => 'true',
                    ],
                    200
                );
            } else {
                return response()->json(
                    [
                        'message' => "Login ou mot de passe incorrect!",
                        'status' => 401,
                        'login' => 'false',
                    ],
                    401
                );
            }


    }
/** 
     * Inscription d'un utilisateur 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function register(Request $request) 
    { 
        $validator = Validator::make($request->all(), [ 
            'name' => 'required',
            'phone' => 'required|numeric|unique:users,phone', 
            'adresse' => 'nullable',
            'description' => 'nullable',
            'avatar' => 'nullable',
            'email' => 'required|email|unique:users,email', 
            'password' => 'required|string|min:6', 
            'c_password' => 'required|same:password',
            'roles' => 'required', 
        ]);
        if ($validator->fails()) { 
                    return response()->json(['error'=>$validator->errors(), 'status'=>400], 400);            
                }

        $input = $request->all(); 
                $input['password'] = bcrypt($input['password']); 
                $user = User::create($input); 
                $user->assignRole($request->input('roles'));
                $success['token'] =  $user->createToken('MyApp')-> accessToken; 
                $success['name'] =  $user->name;
        return response()->json(['success'=>$success, 'status'=>200], $this->successStatus); 
    }


/** 
     * details d'un utilisateur 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function details() 
    { 

        if (Auth::check()) {
            $user = Auth::user(); 
            $userRole = $user->roles->pluck('name','name')->all();
            return response()->json(['success' => $user, 'userRole' => $userRole, 'status'=>200], $this-> successStatus);
         }else{
            return response()->json(['error' => 'impossible de trouver l utilisateur', 'status'=>500]); 
         }        
 
    } 


    /** 
     * si un utilisateur n'est pas connecté 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function not_login() 
    { 
        return response()->json(['error' => 'vous n etes pas connecté', 'status'=>401], 401); 
    } 


    /** 
     * deconnexion d'un utilisateur 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function logout()
    {
        if (Auth::check()) {
            Auth::user()->AauthAcessToken()->delete();
            return response()->json(['success' => 'utilisateur deconnecté', 'status'=>200], $this-> successStatus);
         }else{
            return response()->json(['error' => 'impossible de se deconnecter si on n est pas connecte', 'status'=>500], 500); 
         }
        
    } 

    /** 
     * liste des utilisateurs
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function list()
    {
        if (Auth::check()) {
            $user = User::where('deleted_at', null)->get();
            return response()->json(['success' => $user, 'status'=>200], $this-> successStatus);
         }else{
            return response()->json(['error' => 'Vous n etes pas connecte', 'status'=>500], 500); 
         }
        
    } 

    /** 
     * supprimer un utilisateur
     * 
     * @return \Illuminate\Http\Response 
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
                        'user' => $user, 
                        'status'=>200,
                        'success' => 'true',
                    ],
                    200
                );
            } else {
                // Renvoyer une erreur
                return response()->json(
                    [
                        'message' => 'erreur lors de l archivage', 
                        'status'=>500,
                        'success' => 'false'
                    ],
                    500
                );
            } 
         }else{
            return response()->json(['error' => 'Vous n etes pas connecte', 'status'=>500], 500); 
         }
        
    }


    /** 
     * modification d'un utilisateur 
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function edit_user(Request $request, $id) 
    { 

        //voir si l'utilisateur à modifier existe
        if(!User::Find($id)){

            return response()->json(['error'=>'utilisateur non trouvé', 'status'=>204], 204);
            
        }
        
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'name' => ['required', 'string', 'max:255'],
            'avatar' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif|max:10000'],
            'statut' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email'],
            'adresse' => ['required', 'string', 'max:255'],
            'roles' => ['required'],
            'phone' => ['required', 'numeric', 'max:255'] 

        ]);
        if ($validator->fails()) { 
                    return response()->json(['error'=>$validator->errors(), 'status'=>400], 400);            
                }

        // Récupérer les données validées
        $name = $request->name;
        $description = $request->description;
        $email = $request->email;
        $adresse = $request->adresse;
        $avatar = null;
        if ($request->hasFile('avatar') && $request->file('avatar')->isValid()) {
            $avatar = $request->avatar->store('images/avatars');
        }
        $status = $request->status;
        $phone = $request->phone;

        // Modifier le profil de l'utilisateur
        $user = User::Find($id);
        $user->name = $name;
        if ($avatar != null) {
            $user->avatar = $avatar;
        }
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
                    'user' => $user,
                    'success' => 'true', 
                    'status'=>200,
                ],
                200
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la modification', 
                    'status'=>500,
                    'success' => 'false'
                ],
                500
            );
        } 
    } 


    /**
     * Réinitialisation du mot de passe
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
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
            return response()->json(['error'=>$validator->errors(), 'status'=>400], 400);            
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
                    'user' => $user,
                    'success' => 'true', 
                    'status'=>200,
                ],
                200
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'Echec de réinitialisation du mot de passe', 
                    'status'=>500,
                    'success' => 'false',
                ],
                500
            );
        }
    }





    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        //
    }
}

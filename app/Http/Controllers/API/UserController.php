<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Zone;
use App\Caisse;
use App\Enums\Roles;
use App\Enums\Statut;
use Illuminate\Http\Request;
use App\Agent as Agent_model;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
	/**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $superviseur = Roles::SUPERVISEUR;
        $gestionnaire_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$superviseur|$gestionnaire_flotte");
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
            //'id_zone' => ['nullable', 'array'],
            'description' => 'nullable',
            'email' => 'nullable|email',
            'poste' => ['nullable', 'string', 'max:255'],
            'password' => 'required|string|min:6',
            'id_role' => 'required',
        ]);
        if ($validator->fails()) {
            return response()->json(
                [
                    'message' => "Le formulaire contient des champs mal renseignés",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //dd($request);
        // on verifie si le role est définit
        $role = Role::find($request->id_role);
        if (is_null($role)) {
            return response()->json(
                [
                    'message' => 'ce role n est pas défini',
                    'status' => false,
                    'data' => null
                ]
            );
        }

        /*if (isset($request->id_zone)) {
            foreach ($request->id_zone as $zone) {
                // on verifie si la zone est définie

                    if (!Zone::Find($zone)) {
                        return response()->json(
                            [
                                'message' => 'une zone au moins parmi les zones entrée n est défini',
                                'status' => false,
                                'data' => null
                            ]
                        );
                    }

            }
        }*/

        // Convert base 64 image to normal image for the server and the data base
        //$server_image_name_path = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image'),
            //'images/avatars/');

        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        //$input['avatar'] = $server_image_name_path;
        $input['add_by'] = Auth::user()->id;
        //$input['id_zone'] = json_encode($request->id_zone);
        $user = User::create($input);

        if (isset($user)) {
            //On crée la caisse de l'utilisateur
            $caisse = new Caisse([
                'nom' => 'Caisse ' . $request->name,
                'description' => Null,
                'id_user' => $user->id,
                'reference' => Null,
                'solde' => 0
            ]);
            $caisse->save();

            //on lui donne un role
            $user->assignRole($role);

            //On lui crée un token
            $success['token'] =  $user->createToken('MyApp')->accessToken;
            $success['user'] =  $user;

			// Store user setting
			$user->setting()->create([
				'bars' => '[0,1,2,3,4,5,6,7,8,9]',
				'charts' => '[0,1,2,3,4,5,6,7,8,9]',
				'cards' => '[0,1,2,3,4,5,6,7,8,9]',
			]);

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['user'=>$success]
                ]
            );
        }


        return response()->json(
            [
                'message' => "l'utilisateur n'a pas été créé",
                'status' => false,
                'data' => null
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

            return response()->json([
                'message' => '',
                'status' => true,
                'data' => [
                    'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
                    'role' => $user->roles->first(),
                    'zone' => $user->zone,
                    'puces' => $user->puces,
                    'caisse' => Caisse::where('id_user', $user->id)->first()
                ]
            ]);
         }else{
            return response()->json([
                'message' => "Impossible de trouver l'utilisateur spécifié",
                'status' => false,
                'data' => null
            ]);
         }
    }

    /**
     * //Approuver ou desapprouver un utilisateur
     */
    public function edit_user_status($id)
    {
        $userDB = User::Find($id);
        $user_status = $userDB->statut;

        if ($userDB == null) {
            // Renvoyer un message d'erreur
            return response()->json([
                'message' => "Utilisateur introuvable",
                'status' => true,
                'data' => null
            ]);
        } elseif ($user_status == Statut::DECLINE) {
            // Approuver
            $userDB->statut = Statut::APPROUVE;
        } else {
            // desapprouver
            $userDB->statut = Statut::DECLINE;
        }

        if ($userDB->save()) {
            return response()->json([
                'message' => "Statut de l'utilisateur changé avec succès",
                'status' => true,
                'data' => null
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de la modification du statut',
                'status' => false,
                'data' => null
            ]);
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

            $users = User::where('deleted_at', null)->get();
            $returenedUers = [];

            foreach($users as $user) {

                $returenedUers[] = [
					'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
					'role' => $user->roles->first(),
					'zone' => $user->zone,
                    'puces' => $user->puces,
                    'caisse' => Caisse::where('id_user', $user->id)->first()
				];

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
     * //lister tous les recouveurs
     */
    public function recouvreurs_all()
    {
        $collectors = User::orderBy('created_at', 'desc')->get()->filter(function(User $user) {
            return ($user->roles->first()->name === Roles::RECOUVREUR);
        });

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvreurs' => $this->collectorsResponse($collectors)
            ]
        ]);
    }

    /**
     * //lister des recouveurs
     */
    public function recouvreurs()
    {
        $collectors = User::orderBy('created_at', 'desc')->get()->filter(function(User $user) {
            return ($user->roles->first()->name === Roles::RECOUVREUR);
        });

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvreurs' => $this->collectorsResponse($collectors),
                'hasMoreData' => false,
            ]
        ]);
    }

    /**
     * //lister tous les gestionnaires
     */
    public function gestionnaires_all()
    {
        $managers = User::orderBy('created_at', 'desc')->get()->filter(function(User $user) {
            return ($user->roles->first()->name === Roles::GESTION_FLOTTE);
        });

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'gestionnaires' => $this->managersResponse($managers)
            ]
        ]);
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

        if (!User::find($id)) {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => "L'utilisateur que vous tentez de supprimer n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        if (Auth::check()) {

            if (User::find($id)->delete()) {

				$users = User::get();
				$returenedUers = [];
				foreach($users as $user) {

					$returenedUers[] = [
						'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
						'role' => $user->roles->first(),
						'zone' => $user->zone,
                        'puces' => $user->puces,
                        'caisse' => Caisse::where('id_user', $user->id)->first()
					];

				}

                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => 'utilisateur archivé',
                        'status' => true,
                        'data' => ['users' => $returenedUers]
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
            return response()->json([
                'message' => 'Utilisateur non existant',
                'status' => false,
                'data' => null
            ]);
        }

        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            // 'statut' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            //'poste' => ['nullable', 'string', 'max:255'],
            // 'email' => ['required', 'string', 'email'],
            'adresse' => ['nullable', 'string', 'max:255'],
            // 'roles' => ['required'],
            // 'phone' => ['required', 'numeric', 'max:255']
			'email' => 'nullable|email',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // Récupérer les données validées
        $name = $request->name;
        $description = $request->description;
        $email = $request->email;
        $adresse = $request->adresse;
        // $status = $request->status;
        //$poste = $request->poste;
        // $phone = $request->phone;

        // Modifier le profil de l'utilisateur
        $user = User::Find($id);
        $user->name = $name;
        // $user->statut = $status;
        // $user->phone = $phone;
        //$user->poste = $poste;

        $user->description = $description;
        $user->email = $email;
        $user->adresse = $adresse;

        if ($user->save()) {

            // Renvoyer un message de succès
            return response()->json([
                    'message' => 'Utilisateur mis à jour avec succès',
                    'status' => true,
                    'data' => [
						'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
						'zone' => $user->zone,
                        'puces' => $user->puces,
                        'caisse' => Caisse::where('id_user', $user->id)->first()
					 ]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de la modification',
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * modification du role d'un utilisateur
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

            'id_role' => ['required']

        ]);
        if ($validator->fails()) {
            return response()->json(
                [
                    'message' => "Le formulaire contient des champs mal renseignés",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // on verifie si le role est définit
        $roleExist = Role::find($request->input('id_role'));
        if ($roleExist === null) {
            return response()->json(
                [
                    'message' => 'ce role n est pas défini',
                    'status' => false,
                    'data' => null
                ]
            );
        }


        DB::table('model_has_roles')->where('model_id',$id)->delete();
        $user = User::find($id);

        if ($user->assignRole($roleExist)) {

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Role modifié',
                    'status' => true,
                     'data' => [
						'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
						'role' => $user->roles->first(),
                         'puces' => $user->puces,
                        'caisse' => Caisse::where('id_user', $user->id)->first()
					]
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
     * modification du role d'un utilisateur
     */
    public function edit_zone_user(Request $request, $id)
    {
        //voir si l'utilisateur à modifier existe
        if(!User::Find($id)){

            // Renvoyer un message de notification
            return response()->json([
                'message' => 'Utilisateur non existant',
                'status' => false,
                'data' => null
            ]);
        }

        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_zone' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // on verifie si la zone est définit
        $zoneExist = Zone::find($request->input('id_zone'));
        if ($zoneExist === null) {
            return response()->json([
                'message' => 'Cette zone n est pas défini',
                'status' => false,
                'data' => null
            ]);
        }
        $user = User::find($id);
        // On vérifie s'il ya déjà un responsable dans cette zone
        if($user->roles->first()->name === Roles::RECOUVREUR) {
            if($zoneExist->id_responsable !== null) {
                return response()->json([
                    'message' => 'Un responsable est déjà présent dans cette zone',
                    'status' => false,
                    'data' => null
                ]);
            }
            // Detacher le responsable de l'ancienne zone
            $ancienne_zone = Zone::where('id_responsable', $user->id)->first();
            if($ancienne_zone !== null) {
                $ancienne_zone->id_responsable = null;
                $ancienne_zone->save();
            }
            // Sauvegarder le responsable dans la nouvelle zone
            $zoneExist->id_responsable = $user->id;
            $zoneExist->save();
        }
        //

		$user->id_zone = $request->input('id_zone');

        if ($user->save()) {
            // Renvoyer un message de succès
            return response()->json([
                    'message' => 'Zone mis à jour avec succès',
                    'status' => true,
                     'data' => [
						'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
						'zone' => $user->zone,
                        'puces' => $user->puces,
                        'caisse' => Caisse::where('id_user', $user->id)->first()
					]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de la modification',
                'status' => false,
                'data' => null
            ]);
        }
    }

	/**
     * Creation d'un responsable de zone
     */
    public function create_recouvreur(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'phone' => 'required|numeric|unique:users,phone',
            'adresse' => 'nullable',
            'id_zone' => ['required'],
            'description' => 'nullable',
            'email' => 'nullable|email',
            'password' => 'required|string|min:6',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $role = Role::where('name', Roles::RECOUVREUR)->first();

        $input = $request->all();
        $input['password'] = bcrypt($input['password']);
        //$input['avatar'] = $server_image_name_path;
        $input['add_by'] = Auth::user()->id;
        $input['statut'] = Statut::APPROUVE;
        //$input['id_zone'] = json_encode($request->id_zone);

        // On vérifie s'il ya déjà un responsable dans cette zone
        $zone = Zone::find($input['id_zone']);
        if($zone->id_responsable !== null) {
            return response()->json([
                'message' => 'Un responsable est déjà présent dans cette zone',
                'status' => false,
                'data' => null
            ]);
        }

        $user = User::create($input);
        $zone->id_responsable = $user->id;
        $zone->save();
        //

        if (isset($user)) {
            //On crée la caisse de l'utilisateur
            $caisse = new Caisse([
                'nom' => 'Caisse ' . $request->name,
                'description' => Null,
                'id_user' => $user->id,
                'reference' => Null,
                'solde' => 0
            ]);
            $caisse->save();

            //on lui donne un role
            $user->assignRole($role);

            //On lui crée un token
            $success['token'] =  $user->createToken('MyApp')->accessToken;
            $success['user'] =  $user;

			$user->setting()->create([
				'bars' => '[0,1,2,3,4,5,6,7,8,9]',
				'charts' => '[0,1,2,3,4,5,6,7,8,9]',
				'cards' => '[0,1,2,3,4,5,6,7,8,9]',
			]);

            return response()->json([
                'message' => 'tresponsable de zone crée avec succès',
                'status' => true,
                'data' => [
                    'recouvreur' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
                    'zone' => $user->zone,
                    'caisse' => Caisse::where('id_user', $user->id)->first()
                ]
            ]);
        }

        return response()->json([
            'message' => "Erreur lors de création du responsable de zone",
            'status' => false,
            'data' => null
        ]);
    }

    /**
     * Solde de l'utilisateur connecté
     *
     * @return JsonResponse
     */
    public function solde($id)
    {
        $user = User::find($id);
        $caisse = Caisse::where('id_user', $user->id)->first();
        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['caisse' => $caisse]
            ]
        );

    }

    /**
     * Recuperer le solde de tous les agents
     *
     * @return JsonResponse
     */
    public function agents_soldes()
    {
        $agents = Agent_model::all();
        $caisses = [];
        $n = 1;

        foreach ($agents as $agent) {
            $user = $agent->user;
            $caisse = Caisse::where('id_user', $user->id)->first();
            $caisses[] = ["user $n" => $user, "caisse $n" => $caisse];
            $n = $n + 1;
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['caisses' => $caisses]
            ]
        );

    }

    /**
     * //Recuperer le solde de tous les responsables de zonnes
     *
     * @return JsonResponse
     */
    public function rz_soldes()
    {
        $role = Role::where('name', Roles::RECOUVREUR)->first();
        $recouvreurs = $role->users;
        $caisses = [];
        $n = 1;

        foreach ($recouvreurs as $recouvreurs) {
            $user = $recouvreurs;
            $caisse = Caisse::where('id_user', $user->id)->first();
            $caisses[] = ["user $n" => $user, "caisse $n" => $caisse];
            $n = $n + 1;
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['caisses' => $caisses]
            ]
        );
    }

    // Build collectors return data
    private function collectorsResponse($collectors)
    {
        $returenedCollectors = [];

        foreach($collectors as $collector) {
            $returenedCollectors[] = [
                'recouvreur' => $collector->setHidden(['deleted_at', 'add_by', 'id_zone']),
                'zone' => $collector->zone,
                'puces' => $collector->puces,
                'caisse' => Caisse::where('id_user', $collector->id)->first()
            ];
        }

        return $returenedCollectors;
    }

    // Build managers return data
    private function managersResponse($managers)
    {
        $returenedManagers = [];

        foreach($managers as $manager) {
            $returenedManagers[] = [
                'gestionnaire' => $manager->setHidden(['deleted_at', 'add_by', 'id_zone']),
                'caisse' => Caisse::where('id_user', $manager->id)->first()
            ];
        }

        return $returenedManagers;
    }
}

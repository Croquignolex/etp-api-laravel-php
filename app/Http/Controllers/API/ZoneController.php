<?php

namespace App\Http\Controllers\API;

use App\Enums\Statut;
use App\Zone;
use App\User;
use App\Agent;
use App\Caisse;
use App\Enums\Roles;
use Illuminate\Http\Request;
use App\Utiles\ImageFromBase64;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ZoneController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $agent = Roles::AGENT;
        $comptable = Roles::COMPATBLE;
        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $controlleur = Roles::CONTROLLEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$agent|$controlleur|$comptable");
    }

    /**
     * //Creer une zone.
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'map' => ['nullable', 'string'],
            'description' => ['nullable', 'string']
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
        $reference = $request->reference;
        $map = $request->map;
        $description = $request->description;

        // Nouvelle zone
        $zone = new Zone ([
            'nom' => $name,
            'reference' => $reference,
            'map' => $map,
            'description' => $description
        ]);

        // creation de La zone
        if ($zone->save()) {
            // Renvoyer un message de succès
            return response()->json([
                'message' => 'Zone créer avec succès',
                'status' => true,
                'data' => ['zone' => $zone]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de la Creation',
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * //details d'une zone'
     */
    public function show($id)
    {
        //on recherche la zone en question
        $zone = Zone::find($id);

		$agents = [];
		$users = $zone->users;

		 foreach($users as $user)
		 {
			 $userRole = $user->roles->first()->name;
			 $user_agent = Agent::where('id_user', $user->id)->first();
			 if($userRole === Roles::AGENT)
			 {
				 $agents[] = ['user' => $user, 'agent' => $user_agent];
			 }
		 }

        //Envoie des information
        if(Zone::find($id)){

            return response()->json([
                'message' => '',
                'status' => true,
                'data' => [
                    'zone' => $zone,
                    'agents' => $agents,
                    'recouvreur' => $zone->responsable
                ]
            ]);
        } else {
            return response()->json([
                'message' => "Cette zone n'existe",
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * //Attribuer une zone à un utilisateur'
     */
    public function give_zone(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_user' => ['required', 'Numeric'],
            'id_zone' => ['nullable', 'array']
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

        if (isset($request->id_zone)) {
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
        }

        //on recherche l'utilisateur'
        $user = User::find($request->id_user);
        $user->id_zone = json_encode($request->id_zone);

        //Envoie des information
        if($user->save()){

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
                    'message' => 'ecette zone n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * modification d'une zone
     */
    // SUPERVISOR
    public function update(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'map' => ['nullable', 'string'],
            'description' => ['nullable', 'string']
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
        $reference = $request->reference;
        $map = $request->map;
        $description = $request->description;

        // rechercher la zone
        $zone = Zone::find($id);

        // Modifier la zone
        $zone->nom = $name;
        $zone->reference = $reference;
        $zone->map = $map;
        $zone->description = $description;


        if ($zone->save()) {

			$users = $zone->users;
			$agents = [];
			 foreach($users as $user)
			 {
				 $userRole = $user->roles->first()->name;
				 $user_agent = Agent::where('id_user', $user->id)->first();
				 if($userRole === Roles::AGENT)
				 {
					 $agents[] = ['user' => $user, 'agent' => $user_agent];
				 }
			 }

            // Renvoyer un message de succès
            return response()->json([
                'message' => 'Zone mise à jour avec succès',
                'status' => true,
                'data' => ['zone' => $zone, 'agents' => $agents, 'recouvreur' => $zone->responsable]
            ]);
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
     * ajouter un agent à une zone
     */
    public function delete_agent(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_agent' => ['required', 'numeric']
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

        // Récupérer les données validées
		$id_agent = $request->id_agent;

        // rechercher la flote
        $zone = Zone::find($id);
		$agent = Agent::find($id_agent);
		$user = User::find($agent->id_user);
        $agent->deleted_at = now();
        $user->deleted_at = now();

		$agent->save();
		$user->save();

        if ($agent !== null) {
			$agents = [];
//			$recouvreurs = [];
			$users = $zone->users;
			 foreach($users as $user)
			 {
				 $userRole = $user->roles->first()->name;
				 $user_agent = Agent::where('id_user', $user->id)->first();
				 if($userRole === Roles::AGENT)
				 {
					 $agents[] = ['user' => $user, 'agent' => $user_agent];
					  //$user_zones = json_decode($user->id_zone);
					  //if(array_search($zone->id, $user_zones)) $agents[] = $user;
				 }
				 /*else if($userRole === Roles::RECOUVREUR)
				 {
					 $recouvreurs[] = $user;
					 //$user_zones = json_decode($user->id_zone);
					 //if(array_search($zone->id, $user_zones)) $recouvreurs[] = $user;
				 }*/
			 }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['zone' => $zone, 'agents' => $agents, 'recouvreur' => $zone->responsable]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => "Erreur lors de la suppression d'un agent",
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

	/**
     * ajouter un recouvreur à une zone
     */
    public function ajouter_recouvreur(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'phone' => 'required|numeric|unique:users,phone',
            'adresse' => 'nullable',
            'description' => 'nullable',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'reference' => 'required|string',
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

        // rechercher la Zone
        $zone = Zone::find($id);
		$role = Role::where('name', Roles::RECOUVREUR)->first();
		$input = $request->all();
        $input['password'] = bcrypt($input['password']);
        $input['add_by'] = Auth::user()->id;
        $input['id_zone'] = $id;
		$user = User::create($input);

		$user->setting()->create([
			'bars' => '[0,1,2,3,4,5,6,7,8,9]',
			'charts' => '[0,1,2,3,4,5,6,7,8,9]',
			'cards' => '[0,1,2,3,4,5,6,7,8,9]',
		]);

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
        }


        $agents = [];
        $users = $zone->users;
         foreach($users as $user)
         {
             $userRole = $user->roles->first()->name;
             $user_agent = Agent::where('id_user', $user->id)->first();
             if($userRole === Roles::AGENT)
             {
                 $agents[] = ['user' => $user, 'agent' => $user_agent];
             }
         }

        // Renvoyer un message de succès
        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['zone' => $zone, 'agents' => $agents]
            ]
        );
    }

	/**
     * ajouter un agent à une zone
     */
    public function ajouter_agent(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'phone' => 'required|numeric|unique:users,phone',
            'adresse' => 'nullable',
            'description' => 'nullable',
            'email' => 'nullable',
            'base_64_image' => 'nullable|string',
            'base_64_image_back' => 'nullable|string',
            'document' => 'nullable|file|max:10000',
            'reference' => 'required|string',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

		$name = $request->name;
		$phone = $request->phone;
		$adresse = $request->adresse;
		$description = $request->description;
		$email = $request->email;
		$zone = Zone::find($id);
		$role = Role::where('name', Roles::AGENT)->first();

		$reference = $request->reference;
		$img_cni = null;
		$img_cni_back = null;

		$dossier = null;
		if ($request->hasFile('document') && $request->file('document')->isValid()) {
			$dossier = $request->document->store('files/dossier/agents');
		}

		if (isset($request->base_64_image)) {
			$img_cni = $request->base_64_image;
			$server_image_name_path1 = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image'), 'images/avatars/');
			$img_cni = $server_image_name_path1;
		}
		if (isset($request->base_64_image_back)) {
			$img_cni_back = $request->base_64_image_back;
			$server_image_name_path2 = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image_back'), 'images/avatars/');
			$img_cni_back = $server_image_name_path2;
		}

		$add_by_id = Auth::user()->id;

        // Nouvel utilisateur
		$user = new User([
			'add_by' => $add_by_id,
			'name' => $name,
			'email' => $email,
            'password' => bcrypt("000000"),
			'phone' => $phone,
			'adresse' => $adresse,
			'id_zone' => $zone->id,
			'description' => $description
		]);

		if ($user->save()) {
			$user->setting()->create([
				'bars' => '[0,1,2,3,4,5,6,7,8,9]',
				'charts' => '[0,1,2,3,4,5,6,7,8,9]',
				'cards' => '[0,1,2,3,4,5,6,7,8,9]',
			]);

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

			$agent = new Agent([
				'id_creator' => $add_by_id,
				'id_user' => $user->id,
				'img_cni' => $img_cni,
				'dossier' => $dossier,
				'img_cni_back' => $img_cni_back,
				'reference' => $reference,
                'ville' => "Douala",
                'pays' => "CAMAEROUN"
			]);

			$agent->save();
        }

        if ($user !== null) {
			$agents = [];
			$users = $zone->users;
			 foreach($users as $user)
			 {
				 $userRole = $user->roles->first()->name;
				 $user_agent = Agent::where('id_user', $user->id)->first();
				 if($userRole === Roles::AGENT) $agents[] = ['user' => $user, 'agent' => $user_agent];
			 }

            // Renvoyer un message de succès
            return response()->json([
                'message' => $reference === Roles::AGENT ? "Agent ajouté avec succès" : "Ressource ajoutée avec succès",
                'status' => true,
                'data' => ['zone' => $zone, 'agents' => $agents, 'recouvreur' => $zone->responsable]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => "Erreur l'ors de lsuppression d'un recouvreur",
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * //lister les zone
     */
    public function list()
    {
        $zones = Zone::orderBy('created_at', 'desc')->paginate(6);

        $zones_response =  $this->zonesResponse($zones->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'zones' => $zones_response,
                'hasMoreData' => $zones->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister toutes les zone
     */
    // SUPERVISOR
    public function list_all()
    {
        $zones = Zone::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'zones' => $this->zonesResponse($zones)
            ]
        ]);
    }

    /**
     * //supprimer une zone
     */
    public function destroy($id)
    {
        if (Zone::find($id)) {
            $zoneDB = Zone::find($id);
            $zoneDB->deleted_at = now();
            if ($zoneDB->save()) {

				$zones = Zone::where('deleted_at', null)->get();

				$returenedZone = [];
				foreach($zones as $zone)
				{
					$agents = [];
//					$recouvreurs = [];

					$users = $zone->users;
					 foreach($users as $user)
					 {
						 $userRole = $user->roles->first()->name;
						 $user_agent = Agent::where('id_user', $user->id)->first();
						 if($userRole === Roles::AGENT)
						 {
							 $agents[] = ['user' => $user, 'agent' => $user_agent];
							  //$user_zones = json_decode($user->id_zone);
							  //if(array_search($zone->id, $user_zones)) $agents[] = $user;
						 }
						 /*else if($userRole === Roles::RECOUVREUR)
						 {
							 $recouvreurs[] = $user;
							 //$user_zones = json_decode($user->id_zone);
							 //if(array_search($zone->id, $user_zones)) $recouvreurs[] = $user;
						 }*/
					 }

					$returenedZone[] = ['zone' => $zone, 'agents' => $agents, 'recouvreur' => $zone->responsable];
				}

                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => 'zone archivée',
                        'status' => true,
                        'data' => ['zones' => $returenedZone]
                    ]
                );
            } else {
                // Renvoyer une erreur
                return response()->json(
                    [
                        'message' => 'erreur lors de l archivage',
                        'status' => false,
                        'data' => null
                    ]
                );
            }
         }else{
            return response()->json(
                [
                    'message' => 'cette zone n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }

    /**
     * modification d'une zone
     */
    public function edit_responsable_zone(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_responsable' => ['required', 'Numeric']
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

        //On recupère l'utilisateur passé en paramettre
        $responsable = User::find($request->id_responsable);

        //On verifi si l'utilisateur passé est un responsable de zone
        if (!$responsable->hasRole([Roles::RECOUVREUR])) {
            return response()->json(
                [
                    'message' => "Une zone ne peut etre attribuée qu'à un responsable de zone",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        if (!$zone = Zone::find($id)) {
            return response()->json(
                [
                    'message' => "la zone passée en paramettre n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // Récupérer les données validées
        $id_responsable = $request->id_responsable;

        // Modifier la zone
        $zone->id_responsable = $id_responsable;


        if ($zone->save()) {

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => "l'opération a réussi",
                    'status' => true,
                    'data' => ['zone' => $zone, 'responsable' => $responsable]
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

    // Build zones return data
    private function zonesResponse($zones)
    {
        $returnedZones = [];

        foreach($zones as $zone)
        {
            $returnedZones[] = ['zone' => $zone, 'recouvreur' => $zone->responsable];
        }

        return $returnedZones;
    }
}

<?php

namespace App\Http\Controllers\API;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Agent;
use App\Zone;
use App\Enums\Statut;
use App\Caisse;
use App\User;
use App\Puce;
use Spatie\Permission\Models\Role;
use App\Enums\Roles;
use App\Utiles\ImageFromBase64;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AgentController extends Controller
{
    /**

     * les conditions de lecture des methodes

     */

    function __construct()
    {
        $superviseur = Roles::SUPERVISEUR;
        $recouvreur = Roles::RECOUVREUR;
        $this->middleware("permission:$recouvreur|$superviseur");
    }

    /**
     * creer un Agent
     */
    public function store(Request $request)
    { 

        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 

            //user informations
                'name' => 'required',
                'phone' => 'required|numeric|unique:users,phone',
                'adresse' => 'nullable',
                'description' => 'nullable',
                //'poste' => ['nullable', 'string', 'max:255'],
                'email' => 'required|email|unique:users,email', 
                'password' => 'required|string|min:6', 
                'id_zone' => ['nullable', 'Numeric'],

            //Agent informations
                'base_64_image' => 'nullable|string',
                'base_64_image_back' => 'nullable|string',
                'dossier' => 'nullable|file|max:10000',
                'reference' => ['nullable', 'string', 'max:255'],
                'taux_commission' => ['nullable', 'Numeric'],
                'ville' => ['required', 'string', 'max:255'],
                'pays' => ['required', 'string', 'max:255'],
                'point_de_vente' => ['required', 'string', 'max:255']   

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


        if (isset($request->id_zone)) {
            // on verifie si la zone est définie
                
            if (!Zone::Find($request->id_zone)) {
                return response()->json(
                    [
                        'message' => "la zonne n'est pas definie",
                        'status' => false,
                        'data' => null
                    ]
                ); 
            }
        }

        
        // Récupérer les données validées
            // users
                $name = $request->name;
                $phone = $request->phone;
                $adresse = $request->adresse;
                $description = $request->description;      
                //$poste = $request->poste;
                $email = $request->email;
                $password = bcrypt($request->password);                
                $id_zone = $request->id_zone;

                 $role = Role::where('name', Roles::AGENT)->first();

            // Agent    
            
                $dossier = null;
                if ($request->hasFile('dossier') && $request->file('dossier')->isValid()) {
                    $dossier = $request->dossier->store('files/dossier/agents');
                }
                $reference = $request->reference;
                //$taux_commission = $request->taux_commission;
                $ville = $request->ville;      
                $pays = $request->pays; 
                //$point_de_vente = $request->point_de_vente;
                //$puce_name = $request->puce_name;
                //$puce_number = $request->puce_number;
                $img_cni = null; 
                $img_cni_back = null;             

                if (isset($request->base_64_image)) {
                    $img_cni = $request->base_64_image;
                    // Convert base 64 image to normal image for the server and the data base
                    $server_image_name_path1 = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image'), 
                    'images/avatars/');
                    $img_cni = $server_image_name_path1;
                } 
                if (isset($request->base_64_image_back)) {
                    $img_cni_back = $request->base_64_image_back;
                    // Convert base 64 image to normal image for the server and the data base
                    $server_image_name_path2 = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image_back'), 
                    'images/avatars/');
                    $img_cni_back = $server_image_name_path2;
                }        


        //l'utilisateur connecté
            $add_by_id = Auth::user()->id;
            
        // Nouvel utilisateur
            $user = new User([
                'add_by' => $add_by_id,
                //'poste' => $poste,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'phone' => $phone,
                'adresse' => $adresse,
                'id_zone' => $id_zone,
                'description' => $description
            ]);

        if ($user->save()) {

            //On crée la caisse de l'utilisateur
            $caisse = new Caisse([
                'nom' => 'Caisse ' . $request->name,
                'description' => Null,
                'id_user' => $user->id,
                'reference' => Null,
                'solde' => 0
            ]);
            $caisse->save();

            $user->assignRole($role);
            $user = User::find($user->id);
            //info user à renvoyer
                $success['token'] =  $user->createToken('MyApp')-> accessToken;
                $success['user'] =  $user;
				
			$user->setting()->create([
				'bars' => '[0,1,2,3,4,5,6,7,8,9]',
				'charts' => '[0,1,2,3,4,5,6,7,8,9]',
				'cards' => '[0,1,2,3,4,5,6,7,8,9]',
			]);

            // Nouvel Agent
                $agent = new Agent([
                    'id_creator' => $add_by_id,
                    'id_user' => $user->id,
                    'img_cni' => $img_cni,
                    'dossier' => $dossier,
                    'img_cni_back' => $img_cni_back,
                    'reference' => $reference,
                    //'taux_commission' => $taux_commission,
                    'ville' => $ville,
                    //'point_de_vente' => $point_de_vente,
                    //'puce_name' => $puce_name,
                    //'puce_number' => $puce_number,
                    'pays' => $pays
                ]); 
                
                if ($agent->save()) {

                    //$success['agent'] =  $agent;

                    // Renvoyer un message de succès
                    return response()->json(
                        [
                            'message' => 'agent cree',
                            'status' => true,
                            'data' => ['agent' => $agent]
                        ]
                    );

                } else {
                    // Renvoyer une erreur
                    
                    return response()->json(
                        [
                            'message' => 'erreur lors de la creation',
                            'status' => false,
                            'data' => null
                        ]
                    );
                    
                } 

        }else {
            // Renvoyer un message de erreur
            return response()->json(
                [
                    'message' => 'Problème lors de la creation de l utilisateur correspondant',
                    'status' => false,
                    'data' => null
                ]
            );
        }

    }

    /**
     * details d'un Agent
     */
    public function show($id)
    {
        //on recherche l'agent en question
        $agent = Agent::find($id);


        //Envoie des information
        if(agent::find($id)){
            $user = User::find($agent->id_user);
			$puces = is_null($agent) ? [] : $agent->puces;
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
						'zone' => $user->zone,
						'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']), 
						'agent' => $agent,
						'puces' => $puces 
					]
                ]
            );

        }else{

            return response()->json(
                [
                    'message' => 'cet agent n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
        }
         
    }

    /**
     * Modifier un Agent
     */
    public function edit(Request $request, $id)
    { 
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'reference' => ['nullable', 'string', 'max:255'],
            //'taux_commission' => ['required', 'Numeric'],
            'ville' => ['nullable', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
            'pays' => ['nullable', 'string', 'max:255'],
            //'point_de_vente' => ['required', 'string', 'max:255']
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
        $reference = $request->reference;
        $name = $request->name;
        $ville = $request->ville;      
        $pays = $request->pays; 
        $description = $request->description;
        $adresse = $request->adresse;
         
        // rechercher l'agent
        $agent = Agent::find($id);

        // Modifier le profil de l'utilisateur
        $agent->reference = $reference;
        //$agent->taux_commission = $taux_commission;
        $agent->ville = $ville;
        $agent->pays = $pays;
        //$agent->point_de_vente = $point_de_vente;
		
		$user = User::find($agent->id_user);
		$user->name = $name;
		$user->adresse = $adresse;
		$user->description = $description;
 
        if ($agent->save() && $user->save()) {
			$puces = is_null($agent) ? [] : $agent->puces;
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'agent modifié',
                    'status' => true,
                    'data' => [
						'zone' => $user->zone,
						'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']), 
						'agent' => $agent,
						'puces' => $puces 
					]
                ]
            );
            
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la modification', 
                    'status'=>false,
                    'data' => null
                ]
            );
        } 

    }

    /** 
     * liste des Agents
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function list()
    {

        if (Agent::where('deleted_at', null)) {
            $agents = Agent::where('deleted_at', null)->get();
            $returenedAgents = [];
             	
            foreach($agents as $agent) {

                $user = User::find($agent->id_user);
				 
				$puces = is_null($agent) ? [] : $agent->puces;

                $returenedAgents[] = [ 
					'zone' => $user->zone,
					'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']), 
					'agent' => $agent,
					'puces' => $puces 
				];

            } 

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['agents' => $returenedAgents]
                ]
            );
         }else{
            return response()->json(
                [
                    'message' => 'erreur lors de la modification', 
                    'status'=>false,
                    'data' => null
                ]
            );
         }
        
    } 
	
	/** 
     * //Approuver ou desapprouver un agent
     */ 
    public function edit_agent_status($id) 
    { 
		$agent = Agent::find($id);
        $userDB = User::Find($agent->id_user);
        $user_status = $userDB->statut;

        if ($userDB == null) {

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
            $userDB->statut = Statut::APPROUVE;

            
        }else{

            // desapprouver
            $userDB->statut = Statut::DECLINE;
        }
  
         
        if ($userDB->save()) {
			
			$agents = Agent::where('deleted_at', null)->get();
            $returenedAgents = [];
             	
            foreach($agents as $agent) {

                $user = User::find($agent->id_user);
				 
				$puces = is_null($agent) ? [] : $agent->puces;

                $returenedAgents[] = [ 
					'zone' => $user->zone,
					'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']), 
					'agent' => $agent,
					'puces' => $puces 
				];

            } 

            return response()->json(
                [
                    'message' => 'Statut changé',
                    'status' => true,
                    'data' => ['agents' => $returenedAgents]
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
     * modification la zone de l'agent
     */ 
    public function edit_zone_agent(Request $request, $id) 
    { 
        //voir si l'utilisateur à modifier existe
        if(!Agent::find($id)){

            // Renvoyer un message de notification
            return response()->json(
                [
                    'message' => 'agent non trouvé',
                    'status' => false,
                    'data' => null
                ]
            );
            
        }
        
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 

            'id_zone' => ['required']

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

        // on verifie si la zone est définit
        $zoneExist = Zone::find($request->input('id_zone'));
        if ($zoneExist === null) {
            return response()->json(
                [
                    'message' => 'cette zone n est pas défini',
                    'status' => false,
                    'data' => null
                ]
            ); 
        }
 
		$agent = Agent::find($id); 
        $user = User::Find($agent->id_user);
		$user->id_zone = $request->input('id_zone');

        if ($user->save()) {            
		
			$puces = is_null($agent) ? [] : $agent->puces;

            // Renvoyer un message de succès          
            return response()->json(
                [
                    'message' => 'Zone modifié',
                    'status' => true,
                     'data' => [
						'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
						'zone' => $user->zone,
						'agent' => $agent,
						'puces' => $puces 
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
     * supprimer un Agents
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function delete($id)
    {
        if (Agent::find($id)) {
            $agentDB = Agent::find($id);
            $agentDB->deleted_at = now();
            if ($agentDB->save()) {

                $userDB = User::find($agentDB->id_user);
                $userDB->deleted_at = now();
                $userDB->save();
				
				$agents = Agent::where('deleted_at', null)->get();
				$returenedAgents = [];
					
				foreach($agents as $agent) {

                $user = User::find($agent->id_user);
				 
				$puces = is_null($agent) ? [] : $agent->puces;

                $returenedAgents[] = [ 
					'zone' => $user->zone,
					'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']), 
					'agent' => $agent,
					'puces' => $puces 
				];

            } 

            return response()->json(
                [
                    'message' => 'agent archivé',
                    'status' => true,
                    'data' => ['agents' => $returenedAgents]
                ]
            );
 
		} else {
			// Renvoyer une erreur
			return response()->json(
				[
					'message' => 'erreur lors de l archivage', 
					'status'=>false,
					'data' => null
				]
			);
		} 
         }else{
            return response()->json(
                [
                    'message' => 'cet agent n existe pas', 
                    'status'=>false,
                    'data' => null
                ]
            );
         }
        
    }

    /**
     * @param Base64ImageRequest $request
     * @return JsonResponse
     */
    public function edit_cni(Request $request, $id)
    { 
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'base_64_image' => 'nullable|string',
            'base_64_image_back' => 'nullable|string', 
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
        $agent = Agent::find($id);
 
        $agent_img_cni_path_name =  $agent->img_cni;
        $agent_img_cni_path_name2 =  $agent->img_cni_back;
		
		$img_cni = null;  
		$img_cni_back = null;  

        //Delete old file before storing new file
        if(Storage::exists($agent_img_cni_path_name) && $agent_img_cni_path_name != 'users/default.png')
            Storage::delete($agent_img_cni_path_name);

            //Delete old file before storing new file
        if(Storage::exists($agent_img_cni_path_name2) && $agent_img_cni_path_name2 != 'users/default.png')
        Storage::delete($agent_img_cni_path_name2);
	
		if (isset($request->base_64_image)) {
			$img_cni = $request->base_64_image;
			// Convert base 64 image to normal image for the server and the data base
			$agent_img_cni_path_name = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image'),
            'images/avatars/');
			$img_cni = $agent_img_cni_path_name;
		}
	
		if (isset($request->base_64_image_back)) {
			$img_cni_back = $request->base_64_image_back;
			// Convert base 64 image to normal image for the server and the data base
			$server_image_name_path2 = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image_back'),
            'images/avatars/');
			$img_cni_back = $server_image_name_path2;
		}
 
        // Convert base 64 image to normal image for the server and the data base
        //$server_image_name_path = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image'),
            //'images/avatars/');

        
        // Changer l' avatar de l'utilisateur
        $agent->img_cni = $img_cni;
        $agent->img_cni_back = $img_cni_back;

        // Save image name in database      
        if ($agent->save()) {
			$puces = is_null($agent) ? [] : $agent->puces;
			$user = User::Find($agent->id_user);
            return response()->json(
                [
                    'message' => 'CNI mise à jour avec succes',
                    'status' => true,
                    'data' => [
						'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
						'zone' => $user->zone,
						'agent' => $agent,
						'puces' => $puces 
					]
                ]
            );
        }else {
            return response()->json(
                [
                    'message' => 'erreur de modification de CNI',
                    'status' => true,
                    'data' => ['user'=>$agent]
                ]
            );
        }
        
    }
	
	/**
     * ajouter une puce à un agent
     */
    public function ajouter_puce(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
			'numero' => ['required', 'string', 'max:255', 'unique:puces,numero'],
            'reference' => ['nullable', 'string', 'max:255','unique:puces,reference'], 
            'id_flotte' => ['required', 'numeric'],
            'nom' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'numeric'],
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
		$nom = $request->nom;
        $type = $request->type;
        $numero = $request->numero;
		$id_flotte = $request->id_flotte;
        $reference = $request->reference;
        $description = $request->description;

        // rechercher la flote
        $agent = Agent::find($id);

        // ajout de mla nouvelle puce
        $puce = $agent->puces()->create([
            'nom' => $nom,
			'type' => $type,
			'numero' => $numero,
			'id_flotte' => $id_flotte,
            'reference' => $reference, 
            'description' => $description
		]);

        if ($puce !== null) {
			$user = User::find($agent->id_user);
			$puces = is_null($agent) ? [] : $agent->puces;
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
						'zone' => $user->zone,
						'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']), 
						'agent' => $agent,
						'puces' => $puces 
					]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => "Erreur l'ors de l'ajout de la nouvelle puce",
                    'status' => false,
                    'data' => null
                ]
            );
        } 
    }
	
	/**
     * ajouter une puce à un agent
     */
    public function delete_puce(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [  
            'id_puce' => ['required', 'numeric']
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
		$id_puce = $request->id_puce;
          
        // rechercher la flote
		$puce = Puce::find($id_puce);
        $puce->deleted_at = now();
		$puce->save();
		
        if ($puce !== null) {
			$agent = Agent::find($id);
			$user = User::find($agent->id_user);
			$puces = is_null($agent) ? [] : $agent->puces;
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
						'zone' => $user->zone,
						'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']), 
						'agent' => $agent,
						'puces' => $puces 
					]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => "Erreur l'ors de la suppression d'une puce",
                    'status' => false,
                    'data' => null
                ]
            );
        } 
    }
}

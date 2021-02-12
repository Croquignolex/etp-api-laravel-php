<?php

namespace App\Http\Controllers\API;

use App\Zone;
use App\User;
use App\Puce;
use App\Agent;
use App\Caisse;
use App\Type_puce;
use App\Enums\Roles;
use App\Enums\Statut;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class AgentController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        /*$agent = Roles::AGENT;
        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$agent");*/
    }

    /**
     * creer un Agent
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [

            //user informations
                'name' => 'required',
                'phone' => 'required|numeric',
                'adresse' => 'nullable',
                'description' => 'nullable',
                //'poste' => ['nullable', 'string', 'max:255'],
                'email' => 'nullable|email',
                'password' => 'required|string|min:6',
                'id_zone' => ['nullable', 'Numeric'],

            //Agent informations
                'base_64_image' => 'nullable',
                'base_64_image_back' => 'nullable',
                'document' => 'nullable|file|max:10000',
                'reference' => ['nullable', 'string', 'max:255'],
                //'taux_commission' => ['nullable', 'Numeric'],
                'ville' => ['nullable', 'string', 'max:255'],
                'pays' => ['nullable', 'string', 'max:255'],
                //'point_de_vente' => ['nullable', 'string', 'max:255']
        ]);



        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        if (isset($request->phone)) {
            // on verifie si la zone est définie
            if (User::where('phone', $request->phone)->first()) {
                return response()->json([
                    'message' => "Numéro de téléphone déjà existant",
                    'status' => false,
                    'data' => null
                ]);
            }
        }

        if (isset($request->id_zone)) {
            // on verifie si la zone est définie
            if (!Zone::find($request->id_zone)) {
                return response()->json([
                    'message' => "La zonne n'est pas definie",
                    'status' => false,
                    'data' => null
                ]);
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
                if ($request->hasFile('document') && $request->file('document')->isValid()) {
                    $dossier = $request->document->store('files/dossier/agents');
                }
                $reference = $request->reference;
                //$taux_commission = $request->taux_commission;
                $ville = $request->ville;
                $pays = $request->pays;
                //$point_de_vente = $request->point_de_vente;
                //$puce_name = $request->puce_name;
                //$puce_number = $request->puce_number;

                $img_cni = null;
                if ($request->hasFile('base_64_image') && $request->file('base_64_image')->isValid()) {
                    $img_cni = $request->base_64_image->store('files/CNI_avant/agents');
                }

                $img_cni_back = null;
                if ($request->hasFile('base_64_image_back') && $request->file('base_64_image_back')->isValid()) {
                    $img_cni_back = $request->base_64_image_back->store('files/CNI_arriere/agents');
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
                'statut' => Statut::APPROUVE,
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
            //$user = User::find($user->id);
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
                    return response()->json([
                        'message' => 'Agent crée avec succès',
                        'status' => true,
                        'data' => [
                            'zone' => $user->zone,
                            'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
                            'agent' => $agent,
                            'caisse' => Caisse::where('id_user', $user->id)->first(),
                            'createur' => User::find($user->add_by)
                        ]
                    ]);

                } else {
                    // Renvoyer une erreur

                    return response()->json([
                        'message' => "Erreur l'ors de la creation de l'agent",
                        'status' => false,
                        'data' => null
                    ]);
                }
        } else {
            // Renvoyer un message de erreur
            return response()->json([
                'message' => "Erreur l'ors de la creation de l'agent",
                'status' => false,
                'data' => null
            ]);
        }

    }

    /**
     * details d'un Agent
     * @param $id
     * @return JsonResponse
     */
    public function show($id)
    {
        //on recherche l'agent en question
        $user = User::find($id);
        $agent = $user->agent()->first();

        //Envoie des information
        if($agent != null){
			$puces = is_null($agent) ? [] : $agent->puces;
            return response()->json([
                'message' => '',
                'status' => true,
                'data' => [
                    'zone' => $user->zone,
                    'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
                    'agent' => $agent,
                    'puces' => $puces,
                    'createur' => User::find($user->add_by),
                    'caisse' => Caisse::where('id_user', $user->id)->first()
                ]
            ]);
        } else {
            return response()->json([
                'message' => "Cet agent n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

    }

    /**
     * Modifier un Agent
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function edit(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:255'],
            'adresse' => ['nullable', 'string', 'max:255'],
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
        $email = $request->email;
        $description = $request->description;
        $adresse = $request->adresse;

        // rechercher l'agent

		$user = User::find($id);
        $agent = $user->agent()->first();
		$user->name = $name;
		$user->email = $email;
		$user->adresse = $adresse;
		$user->description = $description;

        if ($user->save()) {
			$puces = is_null($agent) ? [] : $agent->puces;
            // Renvoyer un message de succès
            return response()->json([
                'message' => "Information de l'agent mis à jour avec succès",
                'status' => true,
                'data' => [
                    'zone' => $user->zone,
                    'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
                    'agent' => $agent,
                    'puces' => $puces,
                    'createur' => User::find($user->add_by),
                    'caisse' => Caisse::where('id_user', $user->id)->first()
                ]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de la modification',
                'status'=>false,
                'data' => null
            ]);
        }
    }

    /**
     * Modifier le dossier d'un agent
     * @param Request $request
     * @param Agent $agent
     * @return JsonResponse
     */
    public function edit_folder(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'document' => 'nullable|file|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // Récupérer les données validées
        $dossier = null;
        if ($request->hasFile('document') && $request->file('document')->isValid()) {
            $dossier = $request->document->store('files/dossier/agents');
        }

        $user = User::find($id);
        $agent = $user->agent()->first();
        // Modifier son dossier
        $agent->dossier = $dossier;

        if ($agent->save()) {
            // Renvoyer un message de succès
            //return new AgentResource($agent);

			$puces = is_null($agent) ? [] : $agent->puces;
			$user = User::find($agent->id_user);
            // Renvoyer un message de succès
            return response()->json([
                'message' => "Document agent mis à jour avec succès",
                'status' => true,
                'data' => [
                    'zone' => $user->zone,
                    'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
                    'agent' => $agent,
                    'puces' => $puces,
                    'createur' => User::find($user->add_by),
                    'caisse' => Caisse::where('id_user', $user->id)->first()
                ]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de la modification',
                'status'=>false,
                'data' => null
            ]);
        }
    }

    /**
     * liste des Agents
     *
     * @return JsonResponse
     */
    public function list()
    {
        $agents = Agent::orderBy('created_at', 'desc')->paginate(6);

        $agents_response =  $this->agentsResponse($agents->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'agents' => $agents_response,
                'hasMoreData' => $agents->hasMorePages(),
            ]
        ]);
    }

    /**
     * liste des Agents
     *
     * @return JsonResponse
     */
    public function list_all()
    {
        $agents = Agent::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'agents' => $this->agentsResponse($agents)
            ]
        ]);
    }

    /**
     * //Approuver ou desapprouver un agent
     * @param $id
     * @return JsonResponse
     */
    public function edit_agent_status($id)
    {
        $userDB = User::find($id);
        $user_status = $userDB->statut;

        if ($userDB == null) {

            // Renvoyer un message d'erreur
            return response()->json([
                'message' => 'Utilisateur introuvable',
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
                'message' => "Statut de l'agent changé avec succès",
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
     * modification la zone de l'agent
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function edit_zone_agent(Request $request, $id)
    {
        //voir si l'utilisateur à modifier existe
        if(!User::find($id)){
            // Renvoyer un message de notification
            return response()->json([
                'message' => 'Agent non trouvé',
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
                'message' => "Cette zone n'est pas défini",
                'status' => false,
                'data' => null
            ]);
        }

        $user = User::find($id);
		$agent = $user->agent()->first();
		$user->id_zone = $request->input('id_zone');

        if ($user->save()) {

			$puces = is_null($agent) ? [] : $agent->puces;

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Zone mise à jour avec succès',
                    'status' => true,
                     'data' => [
                         'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
                         'zone' => $user->zone,
                         'agent' => $agent,
                         'puces' => $puces,
                         'createur' => User::find($user->add_by),
                         'caisse' => Caisse::where('id_user', $user->id)->first()
					]
                ]
            );
        } else {

            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'Erreur lors de la modification',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * supprimer un Agents
     *
     * @param $id
     * @return JsonResponse
     */
    public function delete($id)
    {
        if (!User::find($id)) {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => "L'agent que vous tentez de supprimer n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        if (User::find($id)->delete()) {

            $agents = Agent::get();
            $returenedAgents = [];

            foreach($agents as $agent) {

                $user = User::find($agent->id_user);

                $puces = is_null($agent) ? [] : $agent->puces;

                $returenedAgents[] = [
                    'zone' => $user->zone,
                    'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
                    'agent' => $agent,
                    'puces' => $puces,
                    'caisse' => Caisse::where('id_user', $user->id)->first()
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



    }

    /**
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function edit_cni(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'base_64_image' => 'nullable|file|max:10000',
            'base_64_image_back' => 'nullable|file|max:10000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // Get current user
        $user = User::find($id);
        $agent = $user->agent()->first();

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

        $img_cni = null;
        if ($request->hasFile('base_64_image') && $request->file('base_64_image')->isValid()) {
            $img_cni = $request->base_64_image->store('files/CNI_avant/agents');
        }

        $img_cni_back = null;
        if ($request->hasFile('base_64_image_back') && $request->file('base_64_image_back')->isValid()) {
            $img_cni_back = $request->base_64_image_back->store('files/CNI_arriere/agents');
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
                    'message' => "Mise à jour de la CNI avec succès",
                    'status' => true,
                    'data' => [
						'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
						'zone' => $user->zone,
						'agent' => $agent,
						'puces' => $puces,
                        'createur' => User::find($user->add_by),
                        'caisse' => Caisse::where('id_user', $user->id)->first()
					]
                ]
            );
        }else {
            return response()->json([
                'message' => 'Erreur de modification de CNI',
                'status' => true,
                'data' => ['user'=>$agent]
            ]);
        }
    }

    /**
     * ajouter une puce à un agent
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function ajouter_puce(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
			'numero' => ['required', 'string', 'max:255', 'unique:puces,numero'],
            'reference' => ['required', 'string', 'max:255'],
            'id_flotte' => ['required', 'numeric'],
            'nom' => ['required', 'string'],
            'description' => ['nullable', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // Récupérer les données validées
        $reference = $request->reference;

		$nom = $request->nom;
        $type = Type_puce::where('name', $reference)->first()->id;
        $numero = $request->numero;
		$id_flotte = $request->id_flotte;
        $description = $request->description;

        // rechercher l'agent'
        $user = User::find($id);
        $agent = $user->agent()->first();

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
            return response()->json([
                'message' => 'Puce ajoutée avec succès',
                'status' => true,
                'data' => [
                    'zone' => $user->zone,
                    'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
                    'agent' => $agent,
                    'puces' => $puces,
                    'createur' => User::find($user->add_by),
                    'caisse' => Caisse::where('id_user', $user->id)->first()
                ]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => "Erreur l'ors de l'ajout de la nouvelle puce",
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * retirer une puce à un agent
     * @param Request $request
     * @param $id
     * @return JsonResponse
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
                    'message' => "Le formulaire contient des champs mal renseignés",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // Récupérer les données validées
		$id_puce = $request->id_puce;

        // recuperer la puce
		$puce = Puce::find($id_puce);
        $puce->deleted_at = now();
		$puce->save();

        if ($puce !== null) {
            $user = User::find($id);
            $agent = $user->agent()->first();

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
						'puces' => $puces,
                        'caisse' => Caisse::where('id_user', $user->id)->first()
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

    /**
     * // ajouter une puce à un responsable de zonne
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function ajouter_puce_rz(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
			'numero' => ['required', 'string', 'max:255', 'unique:puces,numero'],
            'reference' => ['nullable', 'string', 'max:255','unique:puces,reference'],
            'id_flotte' => ['required', 'numeric'],
            'nom' => ['required', 'string'],
            'description' => ['nullable', 'string'],
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

        //si l'utilisateur n'est pas responsable de zonne'
        $rz = User::find($id);
        if (is_null($rz)) {
            return response()->json(
                [
                    'message' => "Vous devez choisir un Responsable de zonne qui existe",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        if (!($rz->hasRole([Roles::RECOUVREUR]))) {
            return response()->json(
                [
                    'message' => "Vous devez choisir un Responsable de zonne",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //recuperer le type de puce
        $type_puce = Type_puce::where('name', Statut::PUCE_RZ)->first();

        // Récupérer les données validées
		$nom = $request->nom;
        $type = $type_puce->id;
        $numero = $request->numero;
		$id_flotte = $request->id_flotte;
        $reference = $request->reference;
        $description = $request->description;

        // ajout de la nouvelle puce
        $puce = $rz->puces()->create([
            'nom' => $nom,
			'type' => $type,
			'numero' => $numero,
			'id_flotte' => $id_flotte,
            'reference' => $reference,
            'description' => $description
		]);

        if ($puce !== null) {

			$puces = $rz->puces;
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
						'zone' => $rz->zone,
						'user' => $rz->setHidden(['deleted_at', 'add_by', 'id_zone']),
						'puces' => $puces,
                        'caisse' => Caisse::where('id_user', $rz->id)->first()
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
     * retirrer une puce à un responsable de zonne
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function delete_puce_rz(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_puce' => ['required', 'numeric']
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
		$id_puce = $request->id_puce;

        // recuperer la puce
		$puce = Puce::find($id_puce);
        $puce->deleted_at = now();
		$puce->save();

        if ($puce !== null) {
			$rz = User::find($id);
			$puces = is_null($rz) ? [] : $rz->puces;
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
						'zone' => $rz->zone,
						'user' => $rz->setHidden(['deleted_at', 'add_by', 'id_zone']),
						'puces' => $puces,
                        'caisse' => Caisse::where('id_user', $rz->id)->first()
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

    // Build agents return data
    private function agentsResponse($agents)
    {
        $returenedAgents = [];

        foreach($agents as $agent) {

            $user = User::find($agent->id_user);

            $returenedAgents[] = [
                'zone' => $user->zone,
                'user' => $user->setHidden(['deleted_at', 'add_by', 'id_zone']),
                'agent' => $agent,
                'caisse' => Caisse::where('id_user', $user->id)->first(),
                'createur' => User::find($user->add_by)
            ];
        }

        return $returenedAgents;
    }
}

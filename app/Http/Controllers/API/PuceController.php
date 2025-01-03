<?php

namespace App\Http\Controllers\API;

use App\Puce;
use App\User;
use App\Type_puce;
use App\Enums\Roles;
use App\Enums\Statut;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PuceController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
     function __construct(){
         $agent = Roles::AGENT;
         $comptable = Roles::COMPATBLE;
         $responsable = Roles::RECOUVREUR;
         $controlleur = Roles::CONTROLLEUR;
         $superviseur = Roles::SUPERVISEUR;
         $ges_flotte = Roles::GESTION_FLOTTE;
         $this->middleware("permission:$superviseur|$ges_flotte|$responsable|$agent|$controlleur|$comptable");
    }

    /**
     * //Creer une puce.
     * @param Request $request
     * @return JsonResponse
     */
    // ******
    // AGENT
    // RESOURCE
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'numero' => ['required', 'string', 'max:255', 'unique:puces,numero'],
            'reference' => ['nullable', 'string', 'max:255'],
            'id_flotte' => ['required', 'Numeric'],
            'id_agent' => ['nullable', 'Numeric'],
            'id_agency' => ['nullable', 'Numeric'],
            'id_corporate' => ['nullable', 'Numeric'],
            'id_recouvreur' => ['nullable', 'Numeric'],
            'nom' => ['required', 'string'],
            'description' => ['nullable', 'string'],
			'type' => ['required', 'numeric'],
        ]);

        if(Puce::where('numero', $request->numero)->first()) {
            return response()->json([
                'message' => "Ce compte existe déjà dans le système",
                'status' => false,
                'data' => null
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignées ou la puce existe déjà dans le système",
                'status' => false,
                'data' => null
            ]);
        }

        // Récupérer les données validées
        $nom = $request->nom;
		$type = $request->type;
        $numero = $request->numero;
        $reference = $request->reference;

        $id = $request->id_agent;
        $user = is_null($id) ? $id : User::find($id);
        $agent = is_null($id) ? $id : $user->agent->first();
        $id_agent = is_null($id) ? $id : $agent->id;

        $id_corporate = $request->id_corporate;
        $id_recouvreur = $request->id_recouvreur;
        $id_flotte = $request->id_flotte;
        $id_agency = $request->id_agency;
        $description = $request->description;

        // Nouvelle puce
        $puce = new Puce([
            'solde' => 0,
            'nom' => $nom,
			'type' => $type,
            'numero' => $numero,
            'id_agent' => $id_agent,
            'id_agency' => $id_agency,
            'reference' => $reference,
            'id_flotte' => $id_flotte,
            'id_rz' => $id_recouvreur,
            'corporate' => $id_corporate,
            'description' => $description,
        ]);

        // creation de La puce
        if ($puce->save()) {
            // Renvoyer un message de succès
            return response()->json([
                'message' => 'Puce créer avec succès',
                'status' => true,
                'data' => [
                    'puce' => $puce,
                    'flote' => $puce->flote,
                    'type' => $puce->type_puce,
                    'agent' => $agent,
                    'user' => $user,
                    'corporate' => $puce->company,
                    'recouvreur' => $puce->rz,
                    'agency' => $puce->agency,
                ]
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
     * //details d'une puce'
     * @param $id
     * @return JsonResponse
     */
    // ******
    // AGENT
    // RESOURCE
    public function show($id)
    {
        //on recherche la puce en question
        $puce = Puce::find($id);

        if(is_null($puce)) {
            return response()->json([
                'message' => "Cette puce n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $id_agent = $puce->id_agent;
        $agent = is_null($id_agent) ? $id_agent : $puce->agent;
        $user = is_null($id_agent) ? $id_agent : User::find($puce->agent->id_user);
        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puce' => $puce,
                'flote' => $puce->flote,
                'type' => $puce->type_puce,
                'agent' => $agent,
                'user' => $user,
                'corporate' => $puce->company,
                'recouvreur' => $puce->rz,
                'agency' => $puce->agency,
            ]
        ]);
    }

    /**
     * modification d'une puce
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    // ******
    public function update(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'reference' => ['nullable', 'string', 'max:255'],
            'nom' => ['required', 'string'],
            'description' => ['nullable', 'string']
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

        $nom = $request->nom;
        $reference = $request->reference;
        $description = $request->description;

        // rechercher la puce
        $puce = Puce::find($id);

        // Modifier la puce
        $puce->nom = $nom;
        $puce->reference = $reference;
        $puce->description = $description;
        $puce->save();

        $id_agent = $puce->id_agent;
        $agent = is_null($id_agent) ? $id_agent : $puce->agent;
        $user = is_null($id_agent) ? $id_agent : User::find($puce->agent->id_user);
        // Renvoyer un message de succès
        return response()->json(
            [
                'message' => 'Puce mise à jour avec succès',
                'status' => true,
                'data' => [
                    'puce' => $puce,
                    'flote' => $puce->flote,
                    'type' => $puce->type_puce,
                    'agent' => $agent,
                    'user' => $user,
                    'corporate' => $puce->company,
                    'recouvreur' => $puce->rz,
                    'agency' => $puce->agency,
                ]
            ]
        );
    }

    /**
     * modification de l'opérateur de la puce
     */
    // GESTIONNAIRE DE FLOTTE
    public function update_flote(Request $request, $id)
    {
        $puce = Puce::find($id);
        //si la puce n'existe pas
        if (is_null($puce)) {
            return response()->json([
                'message' => "La puce n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_flotte' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // Récupérer les données validées
        $id_flotte = $request->id_flotte;

        // Modifier la puce
        $puce->id_flotte = $id_flotte;

        $puce->save();

        $id_agent = $puce->id_agent;
        $agent = is_null($id_agent) ? $id_agent : $puce->agent;
        $user = is_null($id_agent) ? $id_agent : $agent->user();
        // Renvoyer un message de succès
        return response()->json([
            'message' => "Mise à jour de l'opérateur avec succès",
            'status' => true,
            'data' => [
                'puce' => $puce,
                'flote' => $puce->flote,
                'type' => $puce->type_puce,
                'agent' => $agent,
                'user' => $user,
                'corporate' => $puce->company,
                'recouvreur' => $puce->rz,
                'agency' => $puce->agency,
            ]
        ]);
    }

    /**
     * //lister les puces
     */
    // ******
    public function list()
    {
        $puces = Puce::orderBy('created_at', 'desc')->paginate(6);

        $sims_response =  $this->simsResponse($puces->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $sims_response,
                'hasMoreData' => $puces->hasMorePages(),
            ]
        ]);
    }

    /**
     * //lister toutes les $sims_response
     */
    public function list_all()
    {
        $puces = Puce::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces)
            ]
        ]);
    }

    /**
     * Lister les puces d'un reesponsable de zone
     */
    // RESPONSABLE DE ZONE
    public function list_responsable()
    {
        $user = Auth::user();
        $puces = Puce::where('id_rz', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(6);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces->items()),
                'hasMoreData' => $puces->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister toutes les puces d'un reesponsable de zone
     */
    // RESPONSABLE DE ZONE
    public function list_responsable_all()
    {
        $user = Auth::user();
        $puces = Puce::where('id_rz', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces),
                'hasMoreData' => false,
            ]
        ]);
    }

    /**
     * Lister les puces d'une gestionnaire de flotte
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_gestionnaire()
    {
        $id_puce = Type_puce::where('name', Statut::FLOTTAGE)->first()->id;
        $puces = Puce::where('type', $id_puce)->orderBy('created_at', 'desc')->paginate(6);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces->items()),
                'hasMoreData' => $puces->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister toutes les puces d'une gestionnaire de flotte
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_gestionnaire_all()
    {
        $id_puce = Type_puce::where('name', Statut::FLOTTAGE)->first()->id;
        $puces = Puce::where('type', $id_puce)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces),
                'hasMoreData' => false,
            ]
        ]);
    }

    /**
     * Lister toutes les puces internes à ETP
     */
    // GESTIONNAIRE DE FLOTTE
    // RESPONSABLE DE ZONE
    public function list_internane_all()
    {
        $id_puce_agent = Type_puce::where('name', Statut::AGENT)->first()->id;
        $id_puce_resource = Type_puce::where('name', Statut::RESOURCE)->first()->id;
        $puces = Puce::where('type', '<>', $id_puce_agent)
            ->where('type', '<>', $id_puce_resource)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces),
                'hasMoreData' => false,
            ]
        ]);
    }

    /**
     * Lister toutes les puces externes à ETP
     */
    // RESPONSABLE DE ZONE
    public function list_externane_all()
    {
        $id_puce_agent = Type_puce::where('name', Statut::AGENT)->first()->id;
        $id_puce_resource = Type_puce::where('name', Statut::RESOURCE)->first()->id;
        $puces = Puce::where('type', $id_puce_agent)
            ->orWhere('type', $id_puce_resource)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces),
                'hasMoreData' => false,
            ]
        ]);
    }

    /**
     * Lister les puces master
     */
    // SUPERVISEUR
    public function list_master()
    {
        $id_puce = Type_puce::where('name', Statut::FLOTTAGE_SECONDAIRE)->first()->id;
        $puces = Puce::where('type', $id_puce)->orderBy('created_at', 'desc')->paginate(9);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces->items()),
                'hasMoreData' => $puces->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister toutes les puces master
     */
    // SUPERVISEUR
    public function list_master_all()
    {
        $id_puce = Type_puce::where('name', Statut::FLOTTAGE_SECONDAIRE)->first()->id;
        $puces = Puce::where('type', $id_puce)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces),
                'hasMoreData' => false,
            ]
        ]);
    }

    /**
     * //lister les puces RZ
     */
    public function list_collector()
    {
        $id_puce = Type_puce::where('name', Statut::PUCE_RZ)->first()->id;
        $puces = Puce::where('type', $id_puce)->orderBy('created_at', 'desc')->paginate(6);

        $sims_response =  $this->simsResponse($puces->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $sims_response,
                'hasMoreData' => $puces->hasMorePages(),
            ]
        ]);
    }

    /**
     * //lister toutes les puces de type ressource
     */
    public function list_all_resource_type()
    {
        $id_puce = Type_puce::where('name', Statut::RESOURCE)->first()->id;
        $puces = Puce::where('type', $id_puce)->orderBy('created_at', 'desc')->get();

        $sims_response =  $this->simsResponse($puces);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $sims_response,
                'hasMoreData' => false,
            ]
        ]);
    }

    /**
     * //lister les puces de type agent
     */
    public function list_all_agent_type()
    {
        $id_puce = Type_puce::where('name', Statut::AGENT)->first()->id;
        $puces = Puce::where('type', $id_puce)->orderBy('created_at', 'desc')->paginate(6);

        $sims_response =  $this->simsResponse($puces->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $sims_response,
                'hasMoreData' => $puces->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister les puces d'un agent
     */
    // AGENT
    public function list_agent()
    {
        $user = Auth::user();
        $agent = $user->agent->first();

        $puces = Puce::where('id_agent', $agent->id)->orderBy('created_at', 'desc')->paginate(9);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces->items()),
                'hasMoreData' => $puces->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister les puces d'un agent
     */
    // AGENT
    public function list_agent_all()
    {
        $user = Auth::user();
        $agent = $user->agent->first();

        $puces = Puce::where('id_agent', $agent->id)->orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces),
                'hasMoreData' => false,
            ]
        ]);
    }

    /**
     * //lister les puces
     * @param $id
     * @return JsonResponse
     */
    public function list_puce_flotte($id)
    {
        $puce = Puce::where('id_flotte', $id)
        ->get();

        if ($puce->count() != 0) {
            $puce = Puce::where('id_flotte', $id)
            ->get();
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['puces' => $puce]
                ]
            );
         }else{
            return response()->json(
                [
                    'message' => 'pas de puce à lister',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }

	/**
     * Liste des types de puces
     */
    public function types_puces_list()
    {
        //recuperation des roles
        $types = Type_puce::all();

        if ($types != null) {
            return response()->json([
                'message' => '',
                'status' => true,
                'data' => ['types' => $types]
            ]);
         } else{
            return response()->json([
                'message' => 'Aucun type de puce trouve',
                'status' => false,
                'data' => null
            ]);
         }
    }

    /**
     * liste des puces par raport à la recherche
     *
     * @return JsonResponse
     */
    public function list_search(Request $request)
    {
        $needle = mb_strtolower($request->query('needle'));

        $puces = Puce::orderBy('created_at', 'desc')->get()->filter(function (Puce $puce) use ($needle) {

            $name = mb_strtolower($puce->nom);
            $phone = $puce->numero;

            return (strstr($name, $needle) || strstr($phone, $needle));
        });

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces)
            ]
        ]);
    }

    // Build sims return data
    private function simsResponse($sims)
    {
        $returenedPuces = [];

        foreach($sims as $puce) {

            $id_agent = $puce->id_agent;
            $agent = is_null($id_agent) ? $id_agent : $puce->agent;
            $user = is_null($id_agent) ? $id_agent : User::find($puce->agent->id_user);

            $returenedPuces[] = [
                'puce' => $puce,
                'user' => $user,
                'agent' => $agent,
                'flote' => $puce->flote,
                'corporate' => $puce->company,
                'recouvreur' => $puce->rz,
                'type' => $puce->type_puce,
                'agency' => $puce->agency,
            ];
        }

        return $returenedPuces;
    }
}

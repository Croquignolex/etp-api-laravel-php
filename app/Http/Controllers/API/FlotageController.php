<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Puce;
use App\Role;
use App\Agent;
use App\Caisse;
use App\Type_puce;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Demande_flote;
use App\FlotageAnonyme;
use App\Approvisionnement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Events\NotificationsEvent;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Flottage as Notif_flottage;

class FlotageController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $agent = Roles::AGENT;
        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$agent");
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    // GESTIONNAIRE DE FLOTTE
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_demande_flotte' => ['required', 'numeric'],
            'id_puce' => ['required', 'numeric']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        //Montant du depot
        $montant = $request->montant;
        $demande_flotte = Demande_flote::find($request->id_demande_flotte);

        //On verifi si la demande passée existe réellement
        if (is_null($demande_flotte)) {
            return response()->json([
                'message' => "La demande de flotte n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi que le montant n'est pas supperieur au montant demandé
        if ($demande_flotte->reste < $montant) {
            return response()->json([
                'message' => "Vous essayez d'envoyer plus de flotte que prevu",
                'status' => false,
                'data' => null
            ]);
        }

        // Data
        $puce_etp = Puce::find($request->id_puce);
        $puce_agent = Puce::find($demande_flotte->id_puce);
        $type_puce = Type_puce::find($puce_etp->type)->name;

        // Existance des puces
        if(is_null($puce_etp) || is_null($puce_agent)) {
            return response()->json([
                'message' => "Une des puces n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // capacité de flottage de la puce emetrice
        if($type_puce !== Statut::FLOTTAGE) {
            return response()->json([
                'message' => "Cette puce n'est pas capable d'effectuer un flottage",
                'status' => false,
                'data' => null
            ]);
        }

        // Verification d'opérateur
        if ($puce_etp->id_flotte != $puce_agent->id_flotte) {
            return response()->json([
                'message' => "Les deux puces ne sont pas du même opérateur",
                'status' => false,
                'data' => null
            ]);
        }

        // Solde flotte insufisant
        if($puce_etp->solde < $montant) {
            return response()->json([
                'message' => "Solde flotte insufisant dans cette puce de flottage",
                'status' => false,
                'data' => null
            ]);
        }

        //La gestionnaire concernée
        $connected_user = Auth::user();

        // Nouveau flottage
        $flottage = new Approvisionnement([
            'reste' => $montant,
            'montant' => $montant,
            'from' => $puce_etp->id,
            'statut' => Statut::EN_ATTENTE,
            'id_user' => $connected_user->id,
            'id_demande_flote' => $demande_flotte->id,
        ]);
        $flottage->save();

        //Broadcast Notification des responsables de zone
        $message = "Flottage éffectué par " . $connected_user->nom;
        $role = Role::where('name', Roles::RECOUVREUR)->first();
        $event = new NotificationsEvent($role->id, ['message' => $message]);
        broadcast($event)->toOthers();

        //Database Notification
        $users = User::all();
        foreach ($users as $user)
        {
            if ($user->hasRole([$role->name]))
            {
                $user->notify(new Notif_flottage([
                    'data' => $flottage,
                    'message' => $message
                ]));
            }
        }

        //Database Notification de l'agent
        $demande_flotte->user->notify(new Notif_flottage([
            'message' => $message,
            'data' => $flottage
        ]));

        ////ce que le flottage implique

        //On debite la puce de ETP
        $puce_etp->solde = $puce_etp->solde - $montant;
        $puce_etp->save();

        //On credite la puce de l'Agent
        $puce_agent->solde = $puce_agent->solde + $montant;
        $puce_agent->save();

        //On calcule le reste de flotte à envoyer
        $demande_flotte->reste = $demande_flotte->reste - $montant;
        $demande_flotte->statut = Statut::EFFECTUER ;
        $demande_flotte->source = $puce_etp->id;
        $demande_flotte->save();

        // Renvoyer un message de succès
        return response()->json([
            'message' => "Flottage effectué avec succès",
            'status' => true,
            'data' => null
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    // GESTIONNAIRE DE FOTTE
    public function flottage_express(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'Numeric'],
            'id_agent' => ['required', 'Numeric'],
            'id_puce_agent' => ['required', 'Numeric'],
            'id_puce_flottage' => ['required', 'Numeric']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $user = User::find($request->id_agent);
        //On verifi si l'agent passé existe réellement
        if (is_null($user)) {
            return response()->json([
                'message' => "Cet agent n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $puce_agent = Puce::find($request->id_puce_agent);
        //On verifi si la puce agent passée existe réellement
        if (is_null($puce_agent)) {
            return response()->json([
                'message' => "Cette puce agent n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $puce_etp = Puce::find($request->id_puce_flottage);
        //On verifi si la puce de  flottage passée existe réellement
        if (is_null($puce_etp)) {
            return response()->json([
                'message' => "Cette puce de flottage n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $montant = $request->montant;
        $connected_user = Auth::user();
        // Verification du solde
        if($puce_etp->solde < $montant) {
            return response()->json([
                'message' => "Solde insuffisant dans la puce émétrice",
                'status' => false,
                'data' => null
            ]);
        }

        //On se rassure que la puce passée en paramettre est reelement l'une des puces de flottage sollicités
        if ($puce_etp->id_flotte != $puce_agent->id_flotte) {
            return response()->json([
                'message' => "Les deux puces ne sont pas du même opérateur",
                'status' => false,
                'data' => null
            ]);
        }

        // Récupérer les données pour la création d'une demande fictive de flotte
        $id_user = $user->id;
        $add_by = $connected_user->id;
        $reference = null;
        $statut = Statut::EFFECTUER;
        $source = $request->id_puce_flottage;
        $id_puce = $request->id_puce_agent;

        // Nouvelle demande fictive de flotte
        $demande_flotte = new Demande_flote([
            'id_user' => $id_user,
            'add_by' => $add_by,
            'reference' => $reference,
            'montant' => $montant,
            'reste' => 0,
            'statut' => $statut,
            'id_puce' => $id_puce,
            'source' => $source
        ]);
        $demande_flotte->save();

        // Nouveau flottage
        $flottage = new Approvisionnement([
            'id_demande_flote' => $demande_flotte->id,
            'id_user' => $connected_user->id,
            'statut' => Statut::EN_ATTENTE,
            'from' => $request->id_puce_flottage,
            'montant' => $montant,
            'reste' => $montant
        ]);
        $flottage->save();

        //Broadcast Notification des responsables de zone
        $message = "Flottage éffectué par " . $connected_user->name;
        $role = Role::where('name', Roles::RECOUVREUR)->first();
        $event = new NotificationsEvent($role->id, ['message' => $message]);
        broadcast($event)->toOthers();

        //noifier les responsables de zonne
        $users = User::all();
        foreach ($users as $_user) {

            if ($_user->hasRole([$role->name])) {

                $_user->notify(new Notif_flottage([
                    'data' => $flottage,
                    'message' => $message
                ]));
            }
        }

        //noifier l'agent concerné
        $user->notify(new Notif_flottage([
            'data' => $flottage,
            'message' => $message
        ]));

        ////ce que le flottage implique

        //On debite la puce de ETP
        $puce_etp->solde = $puce_etp->solde - $montant;
        $puce_etp->save();

        //On credite la puce de l'Agent
        $puce_agent->solde = $puce_agent->solde + $montant;
        $puce_agent->save();

        return response()->json([
            'message' => 'Flottage effectué avec succès',
            'status' => true,
            'data' => [
                'approvisionnement' => $flottage,
                'user' => $user,
                'agent' => $user->agent->first(),
                'gestionnaire' => $connected_user,
                'puce_emetrice' => $puce_etp,
                'puce_receptrice' => $puce_agent,
                'operateur' => $puce_etp->flote,
            ]
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    // GESTIONNAIRE DE FOTTE
    public function flottage_anonyme(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'nom_agent' => ['required', 'string'], //le nom de celui qui recoit la flotte
            'id_puce_from' => ['required', 'numeric'],
            'nro_puce_to' => ['required', 'numeric'], //le numéro de la puce qui recoit la flotte
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $puce_from = Puce::find($request->id_puce_from);
        //On verifi si la puce de  flottage passée existe réellement
        if (is_null($puce_from)) {
            return response()->json([
                'message' => "La puce émetrice n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $montant = $request->montant;
        //On se rassure que le solde est suffisant
        if ($puce_from->solde < $montant) {
            return response()->json([
                'message' => "Solde insuffisant dans la puce émetrice",
                'status' => false,
                'data' => null
            ]);
        }

        //L'utilisateur qui envoie
        $connected_user = Auth::user();
        $numero_agent = $request->nro_puce_to;

        // On verrifie si la puce anonyme existe dans la list des puces agents connus
        $agent_sim_type_id = Type_puce::where('name', Statut::AGENT)->get()->first()->id;
        $resource_sim_type_id = Type_puce::where('name', Statut::RESOURCE)->get()->first()->id;

        $needle_sim = Puce::where('numero', $numero_agent)->get()->first();

        if(
            ($needle_sim !== null) && (
                ($needle_sim->type == $agent_sim_type_id) ||
                ($needle_sim->type == $resource_sim_type_id)
            )
        ) {
            //======================================================================
            // Detection
            return response()->json([
                'message' => "Cette puce à été reconnu comme une puce agent/ressource. vous devez plutôt éffectuer un flottage",
                'status' => false,
                'data' => null
            ]);
        } else {
            //======================================================================
            $needle_user = User::where('phone', $numero_agent)->get()->first();

            // Check de l'existence de la puce
            if($needle_sim !== null) {
                return response()->json([
                    'message' => "Cette puce existe déjà dans le système et ne peut être attribuée à un agent/ressource",
                    'status' => false,
                    'data' => null
                ]);
            }

            // Check l'existence de l'utilisateur
            if($needle_user !== null) {
                return response()->json([
                    'message' => "Ce numéro de téléphone est déjà utilisé par un utilisateur comme identifiant",
                    'status' => false,
                    'data' => null
                ]);
            }

            $nom_agent = $request->nom_agent;

            // Creation de l'utilisateur lié à l'agent
            $user = new User([
                'add_by' => $connected_user->id,
                'name' => $nom_agent,
                'password' => bcrypt(000000),
                'phone' => $numero_agent,
                'statut' => Statut::APPROUVE,
            ]);
            $user->save();

            // Creation de la caisse
            $caisse = new Caisse([
                'nom' => 'Caisse ' . $nom_agent,
                'id_user' => $user->id,
                'solde' => 0
            ]);
            $caisse->save();

            // Assigner le role à l'utilisateur
            $role = Role::where('name', Roles::AGENT)->first();
            $user->assignRole($role);

            // Création des paramettres de l'utilisateur
            $user->setting()->create([
                'bars' => '[0,1,2,3,4,5,6,7,8,9]',
                'charts' => '[0,1,2,3,4,5,6,7,8,9]',
                'cards' => '[0,1,2,3,4,5,6,7,8,9]',
            ]);

            // Creation de agent
            $agent = new Agent([
                'id_creator' => $connected_user->id,
                'id_user' => $user->id,
                'reference' => Roles::AGENT,
                'ville' => 'Douala',
                'pays' => 'CAMEROUN'
            ]);
            $agent->save();

            // Création de la puce agent
            $puce = new Puce([
                'nom' => $nom_agent,
                'type' => $agent_sim_type_id,
                'numero' => $numero_agent,
                'id_agent' => $agent->id,
                'reference' => Roles::AGENT,
                'id_flotte' => $puce_from->flote->id,
                'solde' => 0
            ]);
            $puce->save();

            // Récupérer les données pour la création d'une demande fictive de flotte
            $id_user = $user->id;
            $add_by = $user->id;
            $statut = Statut::EFFECTUER;
            $source = $puce_from->id;
            $id_puce = $puce->id;

            // Nouvelle demande fictive de flotte
            $demande_flotte = new Demande_flote([
                'id_user' => $id_user,
                'add_by' => $add_by,
                'montant' => $montant,
                'reste' => 0,
                'statut' => $statut,
                'id_puce' => $id_puce,
                'source' => $source
            ]);
            $demande_flotte->save();

            // Nouveau flottage
            $flottage = new Approvisionnement([
                'id_demande_flote' => $demande_flotte->id,
                'id_user' => $connected_user->id,
                'statut' => Statut::EN_ATTENTE,
                'from' => $puce_from->id,
                'montant' => $montant,
                'reste' => $montant
            ]);
            $flottage->save();

            //Broadcast Notification des responsables de zone
            $message = "Flottage éffectué par " . $connected_user->name;
            $role = Role::where('name', Roles::RECOUVREUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => $message]);
            broadcast($event)->toOthers();

            //noifier les responsables de zonne
            $users = User::all();
            foreach ($users as $_user) {

                if ($_user->hasRole([$role->name])) {

                    $_user->notify(new Notif_flottage([
                        'data' => $flottage,
                        'message' => $message
                    ]));
                }
            }

            //noifier l'agent concerné
            $user->notify(new Notif_flottage([
                'data' => $flottage,
                'message' => $message
            ]));

            ////ce que le flottage implique

            //On credite la puce de l'Agent
            $puce->solde = $puce->solde + $montant;
            $puce->save();

            $puce_from->solde = $puce_from->solde - $montant;
            $puce_from->save();

            return response()->json([
                'message' => 'Flottage éffectué avec succès. Nouvel agent détecté et enreistré avec succès',
                'status' => true,
                'data' => [
                    'approvisionnement' => $flottage,
                    'user' => $user,
                    'agent' => $agent,
                    'gestionnaire' => $connected_user,
                    'puce_emetrice' => $puce_from,
                    'puce_receptrice' => $puce,
                    'operateur' => $puce->flote,
                ]
            ]);
        }
    }

    /**
     * Lister tous les flottages
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_all()
    {
        $demandes_flote = Approvisionnement::orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        $demandes_flotes =  $this->fleetsResponse($demandes_flote->items());

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'flottages' => $demandes_flotes,
                'hasMoreData' => $demandes_flote->hasMorePages(),
            ]
        ]);
    }

    /**
     * ////lister tous les flottages pour un agent
     */
    public function list_all_agent()
    {
        $user = Auth::user();

        //On recupere les Flottages
        $flottages = Approvisionnement::orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(function(Approvisionnement $approvisionnement) use ($user) {
            $demande_de_flotte = $approvisionnement->demande_flote;
            return ($demande_de_flotte->user->id == $user->id);
        });

        $demandes_flotes =  $this->fleetsResponse($flottages);

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'flottages' => $demandes_flotes,
                'hasMoreData' => false,
            ]
        ]);
    }

    /**
     * ////lister tous les flottages pour responsable de zone
     */
    public function list_all_collector($id)
    {
        //On recupere les Flottages
        $flottages = Approvisionnement::get()->filter(function(Approvisionnement $approvisionnement) use ($id) {
            $demande_de_flotte = $approvisionnement->demande_flote;
            return ($demande_de_flotte->add_by == $id);
        });

        $approvisionnements = [];

        foreach($flottages as $flottage) {

            //recuperer la demande correspondante
            $demande = $flottage->demande_flote;

            //recuperer l'agent concerné
            $user = $demande->user;

            //recuperer l'agent concerné
            $agent = Agent::where('id_user', $user->id)->first();

            // recuperer celui qui a effectué le flottage
            $gestionnaire = User::find($flottage->id_user);

            //recuperer la puce de l'agent
            $puce_receptrice = Puce::find($demande->id_puce);

            //recuperer la puce de ETP
            $puce_emetrice = Puce::find($flottage->from);

            $approvisionnements[] = [
                'approvisionnement' => $flottage,
                'demande' => $demande,
                'user' => $user,
                'agent' => $agent,
                'gestionnaire' => $gestionnaire,
                'puce_emetrice' => $puce_emetrice,
                'puce_receptrice' => $puce_receptrice,
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $approvisionnements]
            ]
        );
    }

    /**
     * ////details d'un flottages
     */
    public function show($id_flottage)
    {
        //On recupere le Flottage
        $flottage = Approvisionnement::find($id_flottage);

        //recuperer la demande correspondante
        $demande = $flottage->demande_flote()->first();

        //recuperer celui qui a effectué le flottage
            $user = User::Find($flottage->id_user);

        //recuperer l'agent concerné
            $agent = Agent::where('id_user', $demande->id_user)->get();

        //recuperer la puce de l'agent
            $puce_receptrice = Puce::Find($demande->id_puce);

        $approvisionnements[] = ['approvisionnement' => $flottage,'demande' => $demande, 'user' => $user, 'agent' => $agent, 'puce_receptrice' => $puce_receptrice,];



        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $approvisionnements]
            ]
        );

    }

    /**
     * ////lister les flottages d'une demande
     */
    public function list_flottage($id)
    {
        if (!Demande_flote::Find($id)){

            return response()->json(
                [
                    'message' => "la demande specifiée n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }

        //On recupere les Flottage
        $flottages = Approvisionnement::where('id_demande_flote', $id)->get();


        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $flottages]
            ]
        );

    }

    /**
     * ////détails d'un flottage effectué pour un anonyme
     */
    public function show_flottage_anonyme($id)
    {

        //On recupere la Flottages
        $flottage = FlotageAnonyme::find($id);


        if (!is_null($flottage)){
            //puce de l'envoie
            $puce_envoie = Puce::find($flottage->id_sim_from);
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottage' => $flottage, 'puce_envoie' => $puce_envoie  ]
            ]
        );
    }

    /**
     * ////lister les flottages anonyme effectués par un user precis
     */
    public function flottage_anonyme_by_user($id)
    {
        //On recupere les Flottages anonymes d'un utilisateur
        $flottages_anonymes = FlotageAnonyme::All();

        $flottages = [];

        foreach($flottages_anonymes as $flottage) {

            //puce de l'envoie
            $puce_envoie = Puce::find($flottage->id_sim_from);

            if(($flottage->id_user == $id)) {
                // Take only the current user sending sims
                $flottages[] = [
                    'puce_emetrice' => $puce_envoie,
                    'user' => User::find($id),
                    'flottage' => $flottage
                ];
            }

        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $flottages ]
            ]
        );
    }

    /**
     * ////lister les flottages anonyme effectués par un user precis
     */
    public function list_flottage_anonyme()
    {
        $anonymous = null;

        $connected_user = Auth::user();
        if($connected_user->roles->first()->name === Roles::SUPERVISEUR) {
            $anonymous = FlotageAnonyme::orderBy('created_at', 'desc')
                ->paginate(6);
        } else {
            $anonymous = FlotageAnonyme::where('id_user', $connected_user->id)
                ->where('reference', Statut::FLOTTAGE_ANONYME_GESTIONNAIRE)
                ->orderBy('created_at', 'desc')->paginate(6);
        }

        $anonymous_response =  $this->anonymousResponse($anonymous->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'flottages' => $anonymous_response,
                'hasMoreData' => $anonymous->hasMorePages(),
            ]
        ]);
    }

    // Build anonymous return data
    private function anonymousResponse($anonymous)
    {
        $returnedAnonymous = [];

        foreach($anonymous as $anonyme)
        {
            //puce de l'envoie
            $puce_envoie = Puce::find($anonyme->id_sim_from);

            $returnedAnonymous[] = [
                'puce_emetrice' => $puce_envoie,
                'user' => User::find($anonyme->id_user),
                'flottage' => $anonyme
            ];
        }

        return $returnedAnonymous;
    }

    // Build fleets return data
    private function fleetsResponse($fleets)
    {
        $approvisionnements = [];

        foreach($fleets as $flottage)
        {
            $demande = $flottage->demande_flote;
            $user = $demande->user;
            $agent = $user->agent->first();
            $gestionnaire = $flottage->user;
            $puce_receptrice = $demande->puce;
            $puce_emetrice = $flottage->puce;

            $approvisionnements[] = [
                'approvisionnement' => $flottage,
                'user' => $user,
                'agent' => $agent,
                'gestionnaire' => $gestionnaire,
                'puce_emetrice' => $puce_emetrice,
                'puce_receptrice' => $puce_receptrice,
                'operateur' => $puce_emetrice->flote,
            ];
        }

        return $approvisionnements;
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Recouvrement;
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

        //On verifi si la demande passée existe réellement
        if (!Demande_flote::find($request->id_demande_flotte)) {
            return response()->json([
                'message' => "La demande de flotte n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi que le montant n'est pas supperieur au montant demandé
        if (Demande_flote::find($request->id_demande_flotte)->reste < $request->montant) {
            return response()->json([
                'message' => "Vous essayez d'envoyer plus de flotte que prevu",
                'status' => false,
                'data' => null
            ]);
        }

        // On verifi que la puce passée en paramettre existe
        if (Puce::find($request->id_puce)) {

            //On recupère la puce ETP qui va faire le depot
            $puce_etp = Puce::find($request->id_puce);

            //On recupère la demande à traiter
            $demande_flotte = Demande_flote::find($request->id_demande_flotte);

            //On recupère la puce de l'Agent qui va etre approvisionné
            $puce_agent = Puce::find($demande_flotte->id_puce);

            //on recupère le typ de la puce
            $type_puce = Type_puce::find($puce_etp->type)->name;

            //On se rassure que la puce passée en paramettre est reelement l'une des puces de flottage sollicités
            if ($type_puce == Statut::AGENT || $type_puce == Statut::RESOURCE || $puce_etp->id_flotte != $puce_agent->id_flotte) {
                return response()->json([
                    'message' => "Cette puce n'est pas capable d'effectuer ce flottagage",
                    'status' => false,
                    'data' => null
                ]);
            }

        } else {
            return response()->json([
                'message' => "Cette puce n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //Montant du depot
        $montant = $request->montant;

        //Caisse de l'agent concerné
        $caisse = Caisse::where('id_user', $demande_flotte->id_user)->first();

        //L'agent concerné
        $agent = Agent::where('id_user', $demande_flotte->id_user)->first();

        //La gestionnaire concernée
        $gestionnaire = Auth::user();

        // Nouveau flottage
        $flottage = new Approvisionnement([
            'note' => null,
            'reste' => $montant,
            'reference' => null,
            'montant' => $montant,
            'from' => $puce_etp->id,
            'statut' => Statut::EN_ATTENTE,
            'id_user' => $gestionnaire->id,
            'id_demande_flote' => $demande_flotte->id,
        ]);

        //si l'enregistrement du flottage a lieu
        if ($flottage->save())
        {
            //Broadcast Notification des responsables de zone
            $role = Role::where('name', Roles::RECOUVREUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Nouveau flottage']);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user)
            {
                if ($user->hasRole([$role->name]))
                {
                    $user->notify(new Notif_flottage([
                        'data' => $flottage,
                        'message' => "Nouveau flottage"
                    ]));
                }
            }

            //Database Notification de l'agent
            User::find($demande_flotte->id_user)->notify(new Notif_flottage(['message' => "Nouveau flottage", 'data' => $flottage,]));

            ////ce que le flottage implique

            //On debite la puce de ETP
            $puce_etp->solde = $puce_etp->solde - $montant;
            $puce_etp->save();

            //On credite la puce de l'Agent
            $puce_agent->solde = $puce_agent->solde + $montant;
            $puce_agent->save();

            //On debite la caisse de l'Agent pour le paiement de la flotte envoyée, ce qui implique qu'il doit à ETP
            $caisse->solde = $caisse->solde - $montant;
            $caisse->save();

            //On calcule le reste de flotte à envoyer
            $demande_flotte->reste = $demande_flotte->reste - $montant;

            $demande_flotte->source = $puce_etp->id;

            //On change le statut de la demande de flotte
            if ($demande_flotte->reste == 0) {
                $demande_flotte->statut = Statut::EFFECTUER ;
            } else {
                $demande_flotte->statut = Statut::EN_COURS ;
            }

            //Enregistrer les oppérations
            $demande_flotte->save();

            $user = $demande_flotte->user;
            $demandeur = User::find($demande_flotte->add_by);

            // Renvoyer un message de succès
            return response()->json([
                'message' => "Flottage éffectué avec succès",
                'status' => true,
                'data' => null
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur inatendue lors du flottage',
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    Public function flottage_express(Request $request)
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

        //On verifi si l'agent passé existe réellement
        if (!User::find($request->id_agent)) {
            return response()->json([
                'message' => "Cet Agent n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi si la puce agent passée existe réellement
        if (!Puce::find($request->id_puce_agent)) {
            return response()->json([
                'message' => "Cette puce Agent n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi si la puce de  flottage passée existe réellement
        if (!Puce::find($request->id_puce_flottage)) {
            return response()->json([
                'message' => "Cette puce de flottage n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //recuperer l'agent concerné
        $user = User::find($request->id_agent);
        $agent = $user->agent()->first();
        $connected_user = Auth::user();

        // Récupérer les données pour la création d'une demande fictive de flotte
        $id_user = $user->id;
        $add_by = ($connected_user->roles->first()->name === Roles::RECOUVREUR) ? $connected_user->id : $user->id;
        $reference = null;
        $montant = $request->montant;
        $statut = Statut::EFFECTUER;
        $source = $request->id_puce_flottage;
        //recuperer l'id de puce de l'agent
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

        // On verifi que la puce passée en paramettre existe
        if (Puce::find($request->id_puce_flottage)) {
            //On recupère la puce de l'Agent qui va etre approvisionné
            $puce_agent = Puce::find($request->id_puce_agent);

            //On recupère la puce ETP qui va faire le depot
            $puce_etp = Puce::find($request->id_puce_flottage);

            //on recupère le typ de la puce
            $type_puce = Type_puce::find($puce_etp->type)->name;

            //On se rassure que la puce passée en paramettre est reelement l'une des puces de flottage sollicités
            if ($type_puce == Statut::CORPORATE || $type_puce == Statut::AGENT || $type_puce == Statut::RESOURCE || $puce_etp->id_flotte != $puce_agent->id_flotte) {
                return response()->json([
                    'message' => "Cette puce n'est pas capable d'effectuer un flottagage",
                    'status' => false,
                    'data' => null
                ]);
            }
        }

        // creation de La demande fictive de flotte
        if ($demande_flotte->save()) {

            //Montant du depot
            $montant = $request->montant;

            //Caisse de l'agent concerné
            $caisse = Caisse::where('id_user', $demande_flotte->id_user)->first();

            //La gestionnaire concernée
            //$gestionnaire = Auth::user();

            // Nouveau flottage
            $flottage = new Approvisionnement([
                'id_demande_flote' => $demande_flotte->id,
                'id_user' => $connected_user->id,
                'reference' => null,
                'statut' => Statut::EN_ATTENTE,
                'note' => null,
                'from' => $request->id_puce_flottage,
                'montant' => $montant,
                'reste' => $montant
            ]);

            //si l'enregistrement du flottage a lieu
            if ($flottage->save()) {

                //Broadcast Notification des responsables de zone
                $role = Role::where('name', Roles::RECOUVREUR)->first();
                $event = new NotificationsEvent($role->id, ['message' => 'Nouveau flottage']);
                broadcast($event)->toOthers();

                //Database Notification

                    //noifier l'agent concerné
                    $user->notify(new Notif_flottage([
                        'data' => $flottage,
                        'message' => "Nouveau flottage"
                    ]));


                    //noifier les responsables de zonne
                    $users = User::all();
                    foreach ($users as $_user) {

                        if ($_user->hasRole([$role->name])) {

                            $_user->notify(new Notif_flottage([
                                'data' => $flottage,
                                'message' => "Nouveau flottage"
                            ]));
                        }
                    }

                    //notification de l'agent flotté
                        $user->notify(new Notif_flottage([
                            'data' => $flottage,
                            'message' => "Nouveau flottage"
                        ]));

                ////ce que le flottage implique

                //On debite la puce de ETP
                $puce_etp->solde = $puce_etp->solde - $montant;
                $puce_etp->save();

                //On credite la puce de l'Agent
                $puce_agent->solde = $puce_agent->solde + $montant;
                $puce_agent->save();

                //On debite la caisse de l'Agent pour le paiement de la flotte envoyée, ce qui implique qu'il doit à ETP
                $caisse->solde = $caisse->solde - $montant;
                $caisse->save();

                //recuperer la demande correspondante
                $demande = $flottage->demande_flote;

                //recuperer l'agent concerné
                $user = $demande->user;

                //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->first();

                // recuperer celui qui a éffectué le flottage
                $gestionnaire = User::find($flottage->id_user);

                //recuperer la puce de l'agent
                $puce_receptrice = Puce::find($demande->id_puce);

                //recuperer la puce de ETP
                $puce_emetrice = Puce::find($flottage->from);

                return response()->json([
                    'message' => 'Flottage éffectué avec succès',
                    'status' => true,
                    'data' => [
                        'approvisionnement' => $flottage,
                        'user' => $user,
                        'agent' => $agent,
                        'gestionnaire' => $gestionnaire,
                        'puce_emetrice' => $puce_emetrice,
                        'puce_receptrice' => $puce_receptrice,
                    ]
                ]);
            } else {
                // Renvoyer une erreur
                return response()->json([
                    'message' => 'Erreur lors du flottage',
                    'status' => false,
                    'data' => null
                ]);
            }
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la demande de flotte',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * ////lister tous les flottages
     */
    public function list_all()
    {
        $demandes_flote = Approvisionnement::orderBy('created_at', 'desc')->paginate(6);

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
    public function list_all_agent($id)
    {
        //On recupere les Flottages
        $flottages = Approvisionnement::get()->filter(function(Approvisionnement $approvisionnement) use ($id) {
            $demande_de_flotte = $approvisionnement->demande_flote;
            return ($demande_de_flotte->user->id == $id);
        });

        $approvisionnements = [];

        foreach($flottages as $flottage) {

            //recuperer la demande correspondante
            $demande = $flottage->demande_flote;

            //recuperer l'agent concerné
            $user = $demande->user;

            //recuperer l'agent concerné
            $agent = Agent::where('id_user', $user->id)->first();

            // recuperer celui qui a éffectué le flottage
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

            // recuperer celui qui a éffectué le flottage
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

        //recuperer celui qui a éffectué le flottage
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
     * @param Request $request
     * @return JsonResponse
     * Creer un Flottage pour un anonyme
     */
    Public function flottage_anonyme(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'nom_agent' => ['nullable', 'string'], //le nom de celui qui recoit la flotte
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

        // On verifi que la puce d'envoie passée en paramettre existe
        if (Puce::find($request->id_puce_from)) {

            //On recupère la puce qui envoie
            $puce_from = Puce::find($request->id_puce_from);

            //on recupère les types de la puce qui envoie
            $type_puce_from = Type_puce::find($puce_from->type)->name;

        }else {
            return response()->json([
                'message' => "Une ou plusieurs puces entrées n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //On se rassure que le solde est suffisant
        if ($puce_from->solde < $request->montant) {
            return response()->json([
                'message' => "Le solde de la puce émetrice est insuffisant",
                'status' => false,
                'data' => null
            ]);
        }

        //on debite le solde de celui qui envoie
        $puce_from->solde = $puce_from->solde - $request->montant;

        //L'utilisateur qui envoie
        $user = Auth::user();

        //On credite la caisse de celui qui envoie
        $caisse = $user->caisse()->first();
        $caisse->solde = $caisse->solde + $request->montant;

        // Nouveau flottage
        $flottage_anonyme = new FlotageAnonyme([
            'id_user' => $user->id,
            'id_sim_from' => $puce_from->id,
            'nro_sim_to' => $request->nro_puce_to,
            'reference' => null,
            'statut' => Statut::EFFECTUER,
            'nom_agent' => $request->nom_agent,
            'montant' => $request->montant
        ]);

        //si l'enregistrement du flottage a lieu
        if ($flottage_anonyme->save()) {

            $puce_from->save();
            $caisse->save();

            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $role2 = Role::where('name', Roles::SUPERVISEUR)->first();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {
                if($user->roles->first()->name !== Roles::RECOUVREUR) {
                    if ($user->hasRole([$role->name]) || $user->hasRole([$role2->name])) {

                        $user->notify(new Notif_flottage([
                            'data' => $flottage_anonyme,
                            'message' => "Nouveau flottage anonyme"
                        ]));
                    }
                }
            }

            $puce_envoie = Puce::find($flottage_anonyme->id_sim_from);

            // Renvoyer un message de succès
            return response()->json([
                'message' => "Flottage anonyme éffectué avec succès",
                'status' => true,
                'data' => [
                    'puce_emetrice' => $puce_envoie,
                    'user' => User::find($flottage_anonyme->id_user),
                    'flottage' => $flottage_anonyme
                ]
            ]);
        }else {

            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur perdant le processus de flottage',
                'status' => false,
                'data' => null
            ]);
        }
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
            $anonymous = FlotageAnonyme::orderBy('created_at', 'desc')->paginate(6);
        } else {
            $anonymous = FlotageAnonyme::where('id_user', $connected_user->id)->orderBy('created_at', 'desc')->paginate(6);
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
            //recuperer la demande correspondante
            $demande = $flottage->demande_flote;

            //recuperer l'agent concerné
            $user = $demande->user;

            //recuperer l'agent concerné
            $agent = Agent::where('id_user', $user->id)->first();

            // recuperer celui qui a éffectué le flottage
            $gestionnaire = User::find($flottage->id_user);

            //recuperer la puce de l'agent
            $puce_receptrice = Puce::find($demande->id_puce);

            //recuperer la puce de ETP
            $puce_emetrice = Puce::find($flottage->from);

            $approvisionnements[] = [
                'approvisionnement' => $flottage,
                'user' => $user,
                'agent' => $agent,
                'gestionnaire' => $gestionnaire,
                'puce_emetrice' => $puce_emetrice,
                'puce_receptrice' => $puce_receptrice,
            ];
        }

        return $approvisionnements;
    }
}

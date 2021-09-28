<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Role;
use App\Puce;
use App\Agent;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Demande_destockage;
use Illuminate\Http\Request;
use App\Events\NotificationsEvent;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Demande_destockage as Notif_demande_destockage;

class Demande_destockage_recouvreurController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $controlleur = Roles::CONTROLLEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$controlleur");
    }

    /**
     * Initier une demande de destockage pour un agent
     */
    // RESPONSABLE DE ZONE
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_agent' => ['required', 'numeric'],
            'id_puce' => ['required', 'numeric'] //sous forme de select qui affiche juste les deux puces de type ETP
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        //Coherence du montant de la transaction
        $montant = $request->montant;
        if ($montant <= 0) {
            return response()->json([
                'message' => "Montant de la transaction incohérent",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de l'existence de l'agent
        $agent_user = User::find($request->id_agent);
        if (is_null($agent_user)) {
            return response()->json([
                'message' => "Cet agent n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérifier la puce agent
        $puce = Puce::find($request->id_puce);
        if (is_null($puce)) {
            return response()->json([
                'message' => "Cette puce n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //recuperer l'utilisateur connecté (c'est lui le recouvreur)
        $connected_user = Auth::user();

        // Nouvelle demande de destockage
        $demande_destockage = new Demande_destockage([
            'id_user' => $agent_user->id,
            'add_by' => $connected_user->id,
            'montant' => $montant,
            'reste' => $montant,
            'statut' => Statut::EN_ATTENTE,
            'puce_source' => $puce->id
        ]);
        $demande_destockage->save();

        //Database Notification
        $message = "Nouvelle demande de déstockage éffectué par " . $connected_user->name . " pour " . $agent_user->name;
        $users = User::all();
        foreach ($users as $_user) {
            if ($_user->hasRole([Roles::RECOUVREUR]) && ($user->id !== $connected_user->id)) {
                $_user->notify(new Notif_demande_destockage([
                    'data' => $demande_destockage,
                    'message' => $message
                ]));
            }
        }

        //recuperer l'agent concerné
        $agent = $agent_user->agent->first();

        // Renvoyer un message de succès
        return response()->json([
            'message' => 'Demande de déstockage effectué avec succès',
            'status' => true,
            'data' => [
                'demande' => $demande_destockage,
                'demandeur' => $connected_user,
                'agent' => $agent,
                'user' => $agent_user,
                'puce' => $puce,
                'operateur' => $puce->flote
            ]
        ]);
    }

    /**
     * Lister mes demandes de destockage peu importe le statut
     */
    // GESTIONNAIRE DE FLOTTE
    // RESPONSABLE DE ZONE
    public function list_all_status()
    {
        $demandes_destockage = Demande_destockage::orderBy('created_at', 'desc')->paginate(9);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'demandes' => $this->clearancesResponse($demandes_destockage->items()),
                'hasMoreData' => $demandes_destockage->hasMorePages()
            ]
        ]);
    }

    /**
     * Lister toutes mes demandes de destockage peu importe le statut
     */
    // GESTIONNAIRE DE FLOTTE
    // RESPONSABLE DE ZONE
    public function list_all_status_all()
    {
        $demandes_destockage = Demande_destockage::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'demandes' => $demandes_destockage,
                'hasMoreData' => false
            ]
        ]);
    }

	/**
     * //details d'une demande de destockage'
     */
    public function show($id)
    {
        //on recherche la demande de destockage en question
        $demande_destockage = Demande_destockage::find($id);

        //Envoie des information
        if($demande_destockage != null){

            //recuperer l'utilisateur concerné
                $user = $demande_destockage->user;

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->first();

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demande' => $demande_destockage, 'demandeur' => $user, 'agent' => $agent, 'user' => $user, 'puce' => $demande_destockage->puce]
                ]
            );
        }else{

            return response()->json(
                [
                    'message' => 'ecette demande destockage n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

	/**
     * //Annuler une demande de destockage
     */
    public function annuler(Request $request, $id)
    {
		$demande_destockageDB = Demande_destockage::find($id);
		$demande_destockageDB->statut = \App\Enums\Statut::ANNULE;

        // creation de La demande
        if ($demande_destockageDB->save()) {
			//On recupere les 'demande de flotte'
//			$demandes_destockage = Demande_destockage::where('add_by', Auth::user()->id)->get();
            // tous lister car le responsable de zone peut voir toutes les démande de déstockage et agir en conséquence
            $demandes_destockage = Demande_destockage::all();
			$demandes_destockages = [];

			foreach($demandes_destockage as $demande_destockage) {
				//recuperer l'utilisateur concerné
				$user = $demande_destockage->user;

				//recuperer l'agent concerné
				$agent = Agent::where('id_user', $user->id)->first();

                //recuperer le demandeur
                $demandeur = User::find($demande_destockage->add_by);

				$demandes_destockages[] = ['demande' => $demande_destockage, 'demandeur' => $demandeur, 'agent' => $agent, 'user' => $user, 'puce' => $demande_destockage->puce];
            }

            //Broadcast Notification
            $role = Role::where('name', Roles::RECOUVREUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Une demande de Destockage Annulée']);
            broadcast($event)->toOthers();


            //Database Notification
            $users = User::all();
            foreach ($users as $_user) {

                if ($_user->hasRole([$role->name])) {

                    $_user->notify(new Notif_demande_destockage([
                        'data' => $demande_destockageDB,
                        'message' => "Une demande de Destockage Annulée"
                    ]));

                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Demande de déstockage annulée',
                    'status' => true,
                    'data' => ['demandes' => $demandes_destockages]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la demande',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

	/**
     * //modifier une demande de destockage
     */
    public function modifier(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
			'id_agent' => ['required', 'numeric'],
            'id_puce' => ['required', 'numeric'] //sous forme de select qui affiche juste les deux puces MTN et ORANGE créé par seed
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

		$demande_destockage = Demande_destockage::find($id);
		$demande_destockage->montant = $request->montant;
		$demande_destockage->reste = $request->montant;
		$demande_destockage->puce_source = $request->id_puce;
		$agent = Agent::find($request->id_agent);
		$demande_destockage->id_user = $agent->id_user;

        // update de La demande
        if ($demande_destockage->save()) {
            $user = $demande_destockage->user;

            //Broadcast Notification
            $role = Role::where('name', Roles::RECOUVREUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Une demande de Destockage modifiée']);
            broadcast($event)->toOthers();


            //Database Notification
            $users = User::all();
            foreach ($users as $_user) {

                if ($_user->hasRole([$role->name])) {

                    $_user->notify(new Notif_demande_destockage([
                        'data' => $demande_destockage,
                        'message' => "Une demande de Destockage modifiée"
                    ]));

                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demande' => $demande_destockage, 'demandeur' => $user, 'agent' => $agent, 'user' => $user, 'puce' => $demande_destockage->puce]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la demande',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * Reponse une demande de destockage
     */
    // RESPONSABLE DE ZONE
    public function reponse(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        //Coherence du montant de la transaction
        $montant = $request->montant;
        if ($montant <= 0) {
            return response()->json([
                'message' => "Montant de la transaction incohérent",
                'status' => false,
                'data' => null
            ]);
        }

        $demande_destockage = Demande_destockage::find($id);
        $reste = $demande_destockage->reste;

        if($reste < $montant) {
            return response()->json([
                'message' => "Vous ne pouvez pas prendre en charge au délas de la demande",
                'status' => false,
                'data' => null
            ]);
        }

        $reste = $reste - $montant;
        $demande_destockage->reste = $reste;
        $demande_destockage->statut = $reste == 0 ? Statut::EFFECTUER : Statut::EN_COURS;

        $demande_destockage->save();

        // Renvoyer un message de succès
        return response()->json([
            'message' => "Prise en charge effectuée avec succès",
            'status' => true,
            'data' => null
        ]);
    }

    // Build clearances return data
    private function clearancesResponse($clearances)
    {
        $demandes_destockages = [];

        foreach($clearances as $demande_destockage)
        {
            $user = $demande_destockage->user;
            $agent = $user->agent->first();
            $demandeur = $demande_destockage->creator;
            $puce = $demande_destockage->puce;

            $demandes_destockages[] = [
                'demande' => $demande_destockage,
                'demandeur' => $demandeur,
                'agent' => $agent,
                'user' => $user,
                'puce' => $puce,
                'operateur' => $puce->flote
            ];
        }

        return $demandes_destockages;
    }
}

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
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Demande_destockage as Notif_demande_destockage;

class DemandedestockageController extends Controller
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
        $this->middleware("permission:$recouvreur|$agent|$superviseur|$ges_flotte");
    }

    /**
     * Initier une demande de destockage
     */
    // AGENT
    // RESSOURCE
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_puce' => ['required', 'numeric']
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

        // Vérifier la puce agent
        $puce = Puce::find($request->id_puce);
        if (is_null($puce)) {
            return response()->json([
                'message' => "Cette puce n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de l'appartenance de la puce
        $connected_user = Auth::user();
        if ($puce->agent->user->id !== $connected_user->id) {
            return response()->json([
                'message' => "Cette puce ne vous appartient pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Nouvelle demande de destockage
        $demande_destockage = new Demande_destockage([
            'id_user' => $connected_user->id,
            'add_by' => $connected_user->id,
            'montant' => $montant,
            'reste' => $montant,
            'statut' => Statut::EN_ATTENTE,
            'puce_source' => $puce->id
        ]);
        $demande_destockage->save();

        //Database Notification
        $message = "Nouvelle demande de déstockage éffectué par " . $connected_user->name;
        $users = User::all();
        foreach ($users as $user) {
            if ($user->hasRole([Roles::RECOUVREUR])) {
                $user->notify(new Notif_demande_destockage([
                    'data' => $demande_destockage,
                    'message' => $message
                ]));
            }
        }

        //recuperer l'agent concerné
        $agent = $connected_user->agent->first();

        // Renvoyer un message de succès
        return response()->json([
            'message' => 'Demande de déstockage effectué avec succès',
            'status' => true,
            'data' => [
                'demande' => $demande_destockage,
                'demandeur' => $connected_user,
                'agent' => $agent,
                'user' => $connected_user,
                'puce' => $puce,
                'operateur' => $puce->flote,
            ]
        ]);
    }

	/**
     * //modifier une demande de Flotte
     */
    public function modifier(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
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

        // update de La demande
        if ($demande_destockage->save()) {
            //recuperer l'utilisateur concerné
            $user = $demande_destockage->user;

            //recuperer l'agent concerné
            $agent = Agent::where('id_user', $user->id)->first();

            $demandeur = User::find($demande_destockage->add_by);

            //Broadcast Notification
            $role = Role::where('name', Roles::RECOUVREUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Une demande de Destockage modifiée']);
            broadcast($event)->toOthers();


            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_demande_destockage([
                        'data' => $demande_destockage,
                        'message' => "Une demande de Destockage modifiée"
                    ]));

                }
            }

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'demande' => $demande_destockage,
                        'demandeur' => $demandeur,
                        'agent' => $agent,
                        'user' => $user,
                        'puce' => $demande_destockage->puce
                    ]
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
     * Lister mes demandes de destockage peu importe le statut
     */
    // AGENT
    // RESSOURCE
    public function list_all_status()
    {
        $user = Auth::user();
        $demandes_destockage = Demande_destockage::where('id_user', $user->id)
            ->orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(9);

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
     * Lister mes demandes de destockage peu importe le statut
     */
    // AGENT
    // RESSOURCE
    public function list_all_status_all()
    {
        $user = Auth::user();
        $demandes_destockage = Demande_destockage::where('id_user', $user->id)
            ->orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'demandes' => $this->clearancesResponse($demandes_destockage),
                'hasMoreData' => false
            ]
        ]);
    }

	/**
     * //details d'une demande de flote'
     */
    public function show($id)
    {
        //on recherche la demande de flote en question
        $demande_destockage = Demande_destockage::find($id);

        //Envoie des information
        if($demande_destockage != null){

            //recuperer l'utilisateur concerné
                $user = $demande_destockage->user;

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->first();

			$demandeur = User::find($demande_destockage->add_by);

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
						'demande' => $demande_destockage,
						'demandeur' => $demandeur,
						'agent' => $agent,
						'user' => $user,
						'puce' => $demande_destockage->puce
					]
                ]
            );

        }else{

            return response()->json(
                [
                    'message' => 'ecette demande flote n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

	/**
     * //Annuler une demande de flotte
     */
    public function annuler(Request $request, $id)
    {
		$demande_destockageDB = Demande_destockage::find($id);
		$demande_destockageDB->statut = \App\Enums\Statut::ANNULE;

        // creation de La demande
        if ($demande_destockageDB->save()) {
			//On recupere les 'demande de destockage'
			$demandes_destockage = Demande_destockage::where('id_user', Auth::user()->id)->get();

			$demandes_destockages = [];

			foreach($demandes_destockage as $demande_destockage) {
				//recuperer l'utilisateur concerné
				$user = $demande_destockage->user;

				//recuperer l'agent concerné
				$agent = Agent::where('id_user', $user->id)->first();

				//recuperer le demandeur
				$demandeur = User::Find($demande_destockage->add_by);

				$demandes_destockages[] = ['demande' => $demande_destockage, 'demandeur' => $demandeur, 'agent' => $agent, 'user' => $user, 'puce' => $demande_destockage->puce];
            }


            //Broadcast Notification
            $role = Role::where('name', Roles::RECOUVREUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Une demande de Destockage Annulée']);
            broadcast($event)->toOthers();


            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_demande_destockage([
                        'data' => $demandes_destockage,
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

    // Build clearances return data
    private function clearancesResponse($clearances)
    {
        $demandes_destockages = [];

        foreach($clearances as $demande_destockage)
        {
            //recuperer l'utilisateur concerné
            $user = $demande_destockage->user;

            //recuperer l'agent concerné
            $agent = Agent::where('id_user', $user->id)->first();

            //recuperer le demandeur
            $demandeur = User::find($demande_destockage->add_by);

            $demandes_destockages[] = [
                'demande' => $demande_destockage,
                'demandeur' => $demandeur,
                'agent' => $agent,
                'user' => $user,
                'puce' => $demande_destockage->puce,
                'operateur' => $demande_destockage->puce->flote
            ];
        }

        return $demandes_destockages;
    }
}

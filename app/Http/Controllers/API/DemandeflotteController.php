<?php

namespace App\Http\Controllers\API;

use App\Agent;
use App\User;
use App\Role;
use App\Puce;
use \App\Enums\Roles;
use App\Demande_flote;
use Illuminate\Http\Request;
use App\Events\NotificationsEvent;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Demande_flotte as Notif_demande_flotte;

class DemandeflotteController extends Controller
{
    /**

     * les conditions de lecture des methodes

     */
    function __construct(){
        $recouvreur = Roles::RECOUVREUR;
        $agent = Roles::AGENT;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$agent|$superviseur|$ges_flotte");
    }

    /**
     * //Initier une demande de Flotte
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'fdf' => ['required', 'numeric'],
            'dfddd' => ['required', 'numeric'],
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

        if (!($puce = Puce::Find($request->id_puce))) {
            return response()->json(
                [
                    'message' => "Cette puce n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //recuperer l'utilisateur connecté (c'est lui l'agent)
        $user = Auth::user();

        if (!($puce->agent->user->id ==  $user->id)) {
            return response()->json(
                [
                    'message' => "La puce selectionnée doit vous appartenir",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // Récupérer les données validées
        $id_user = $user->id;
        $add_by = $user->id;
        $reference = null;
        $montant = $request->montant;
        $statut = \App\Enums\Statut::EN_ATTENTE;
        $id_puce = $request->id_puce;

        // Nouvelle demande de flotte
        $demande_flote = new Demande_flote([
            'id_user' => $id_user,
            'add_by' => $add_by,
            'reference' => $reference,
            'montant' => $montant,
            'reste' => $montant,
            'statut' => $statut,
            'source' => null,
			'id_puce' => $id_puce,
        ]);

        // creation de La demande
        if ($demande_flote->save()) {

            ////Broadcast Notification
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Nouvelle demande flotte']);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_demande_flotte([
                        'data' => $demande_flote,
                        'message' => "Nouvelle demande de flotte"
                    ]));
                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Demande de Flote créée',
                    'status' => true,
                    'data' => ['demande' => $demande_flote]
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
     * //modifier une demande de Flotte
     */
    public function modifier(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'Numeric'],
            'id_puce' => ['required', 'Numeric'] //sous forme de select qui affiche juste les deux puces MTN et ORANGE créé par seed
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

		$demande_flote = Demande_flote::find($id);
		$demande_flote->montant = $request->montant;
		$demande_flote->reste = $request->montant;
		$demande_flote->id_puce = $request->id_puce;

        // update de La demande
        if ($demande_flote->save()) {
			$user = $demande_flote->user;
			$agent = Agent::where('id_user', $user->id)->first();
            $demandeur = User::Find($demande_flote->add_by);

            ////Broadcast Notification
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $event = new NotificationsEvent($role->id, ['message' => "Modification d'une emande de flotte"]);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_demande_flotte([
                        'data' => $demande_flote,
                        'message' => "Modification d'une emande de flotte"
                    ]));
                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'demande' => $demande_flote,
                        'demandeur' => $demandeur,
                        'agent' => $agent,
                        'user' => $user,
                        'approvisionnements' => $demande_flote->approvisionnements,
                        'puce' => $demande_flote->puce
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
     * //modifier une demande de Flotte (gestionnaire de flotte ou les admin)
     */
    public function modifier_general(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'reference' => ['required', 'string'],
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

		$demande_flote = Demande_flote::find($id);
		$demande_flote->reference = $request->reference;

        // update de La demande
        if ($demande_flote->save()) {
			$user = $demande_flote->user;
			$agent = Agent::where('id_user', $user->id)->first();
            $demandeur = User::Find($demande_flote->add_by);


            ////Broadcast Notification
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $event = new NotificationsEvent($role->id, ['message' => "Modification d'une demande de flotte"]);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_demande_flotte([
                        'data' => $demande_flote,
                        'message' => "Modification d'une emande de flotte"
                    ]));
                }
            }



            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
						'demande' => $demande_flote,
						'demandeur' => $demandeur,
						'agent' => $agent,
						'user' => $user,
						'approvisionnements' => $demande_flote->approvisionnements,
						'puce' => $demande_flote->puce
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
     * //lister mes demandes de flotes peu importe le statut
     */
    public function list_all_status()
    {
        //On recupere les 'demande de flotte'
        $demandes_flote = Demande_flote::where('id_user', Auth::user()->id)->get();

		$demandes_flotes = [];

        foreach($demandes_flote as $demande_flote) {
			//recuperer l'utilisateur concerné
            $user = $demande_flote->user;

            //recuperer l'agent concerné
            $agent = Agent::where('id_user', $user->id)->first();

            //recuperer le demandeur
			$demandeur = User::Find($demande_flote->add_by);

            $demandes_flotes[] = ['demande' => $demande_flote, 'demandeur' => $demandeur, 'agent' => $agent, 'user' => $user, 'puce' => $demande_flote->puce];
        }

		return response()->json(
			[
				'message' => '',
				'status' => true,
				'data' => ['demandes' => $demandes_flotes]
			]
		);

    }

    /**
     * //lister mes demandes de flotes (gestionnaire de flotte ou les admin)
     */
    public function list_demandes_flote_general()
    {
        $demandes_flote = Demande_flote::orderBy('created_at', 'desc')->paginate(10);

        $demandes_flotes = [];

        foreach($demandes_flote->items() as $demande_flote) {
			//recuperer l'utilisateur concerné
            $user = $demande_flote->user;

            //recuperer l'agent concerné
            $agent = Agent::where('id_user', $user->id)->first();

            //recuperer le demandeur
			$demandeur = User::find($demande_flote->add_by);

            $demandes_flotes[] = ['demande' => $demande_flote, 'demandeur' => $demandeur, 'agent' => $agent, 'user' => $user, 'puce' => $demande_flote->puce];
        }

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' => $demandes_flotes,
                'hasMoreData' => $demandes_flote->hasMorePages(),
            ]
        ]);
    }

    /**
     * //details d'une demande de flote'
     */
    public function show($id)
    {
        //on recherche la demande de flote en question
        $demande_flote = Demande_flote::find($id);

        //Envoie des information
        if($demande_flote != null){

            //recuperer l'utilisateur concerné
                $user = $demande_flote->user;

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->first();

            //recuperer la flotte concerné
                //$flote = Flote::Find($demande_flote->user_source);

            //recuperer la puce de l'agent
            /*$puce = Puce::where('id_flotte', $flote->id)
            ->where('id_agent', $agent->id)
            ->First();*/
			$demandeur = User::Find($demande_flote->add_by);

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
						'demande' => $demande_flote,
						'demandeur' => $demandeur,
						'agent' => $agent,
						'user' => $user,
						'approvisionnements' => $demande_flote->approvisionnements,
						'puce' => $demande_flote->puce
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
		$demande_floteDB = Demande_flote::find($id);
		$demande_floteDB->statut = \App\Enums\Statut::ANNULE;

        // creation de La demande
        if ($demande_floteDB->save()) {
			//On recupere les 'demande de flotte'
			$demandes_flote = Demande_flote::where('id_user', Auth::user()->id)->get();

			$demandes_flotes = [];

			foreach($demandes_flote as $demande_flote) {
				//recuperer l'utilisateur concerné
				$user = $demande_flote->user;

				//recuperer l'agent concerné
				$agent = Agent::where('id_user', $user->id)->first();

				//recuperer le demandeur
				$demandeur = User::Find($demande_flote->add_by);

				$demandes_flotes[] = ['demande' => $demande_flote, 'demandeur' => $demandeur, 'agent' => $agent, 'user' => $user, 'puce' => $demande_flote->puce];
            }

            ////Broadcast Notification
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $event = new NotificationsEvent($role->id, ['message' => "Annulation d'une emande de flotte"]);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_demande_flotte([
                        'data' => $demande_floteDB,
                        'message' => "Annulation d'une emande de flotte"
                    ]));
                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Demande de Flote annulée',
                    'status' => true,
                    'data' => ['demandes' => $demandes_flotes]
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
}

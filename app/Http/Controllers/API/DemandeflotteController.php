<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Role;
use App\Puce;
use App\Agent;
use App\Enums\Statut;
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
        $agent = Roles::AGENT;
        $recouvreur = Roles::RECOUVREUR;
        $controlleur = Roles::CONTROLLEUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$agent|$superviseur|$ges_flotte|$controlleur");
    }

    /**
     * Initier une demande de Flotte
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

        // Nouvelle demande de flotte
        $demande_flote = new Demande_flote([
            'id_user' => $connected_user->id,
            'add_by' => $connected_user->id,
            'montant' => $montant,
            'reste' => $montant,
            'statut' => Statut::EN_ATTENTE,
			'id_puce' => $puce->id,
        ]);
        $demande_flote->save();

        //Database Notification
        $message = "Nouvelle demande de flotte éffectué par " . $connected_user->name;
        $users = User::all();
        foreach ($users as $user) {
            if ($user->hasRole([Roles::GESTION_FLOTTE])) {
                $user->notify(new Notif_demande_flotte([
                    'data' => $demande_flote,
                    'message' => $message
                ]));
            }
        }

        //recuperer l'agent concerné
        $agent = $connected_user->agent->first();

        // Renvoyer un message de succès
        return response()->json([
            'message' => 'Demande de flotte éffectuée avec succès',
            'status' => true,
            'data' => [
                'demande' => $demande_flote,
                'demandeur' => $connected_user,
                'agent' => $agent,
                'user' => $connected_user,
                'puce' => $puce,
                'operateur' => $puce->flote
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
     * Lister mes demandes de flotes peu importe le statut
     */
    // AGENT
    // RESSOURCE
    public function list_all_status()
    {
        $user = Auth::user();
        $demandes_flote = Demande_flote::where('id_user', $user->id)
            ->orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' => $this->fleetsResponse($demandes_flote->items()),
                'hasMoreData' => $demandes_flote->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister mes demandes de flotes (gestionnaire de flotte ou les admin)
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_demandes_flote_general()
    {
        $demandes_flote = Demande_flote::orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        $demandes_flotes = $this->fleetsResponse($demandes_flote->items());

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
     * lister mes demandes de flotes responsable de zone
     */
    // RESPONSABLE DE ZONE
    public function list_demandes_flote_collector()
    {
        $user = Auth::user();
        $demandes_flote = Demande_flote::where('add_by', $user->id)
            ->orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' =>  $this->fleetsResponse($demandes_flote->items()),
                'hasMoreData' => $demandes_flote->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister toutes mes demandes de flotes (responsable de zone)
     */
    // RESPONSABLE DE ZONE
    public function list_demandes_flote_collector_all()
    {
        $user = Auth::user();
        $demandes_flote = Demande_flote::where('add_by', $user->id)
            ->orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' => $this->fleetsResponse($demandes_flote),
                'hasMoreData' => false
            ]
        ]);
    }

    /**
     * Lister toutes mes demandes de flotes (agent)
     */
    // AGENT
    // RESSOURCE
    public function list_demandes_flote_agent_all()
    {
        $user = Auth::user();
        $demandes_flote = Demande_flote::where('id_user', $user->id)
            ->orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' => $this->fleetsResponse($demandes_flote),
                'hasMoreData' => false
            ]
        ]);
    }

    /**
     * lister toutes mes demandes de flotes (gestionnaire de flotte ou les admin)
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_demandes_flote_general_all()
    {
        $demandes_flote = Demande_flote::orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' => $this->fleetsResponse($demandes_flote)
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

    // Build fleets return data
    private function fleetsResponse($fleets)
    {
        $demandes_flotes = [];

        foreach($fleets as $demande_flote) {
            $user = $demande_flote->user;
            $agent = $user->agent->first();
            $demandeur = $demande_flote->creator;

            $demandes_flotes[] = [
                'demande' => $demande_flote,
                'demandeur' => $demandeur,
                'agent' => $agent,
                'user' => $user,
                'puce' => $demande_flote->puce,
                'operateur' => $demande_flote->puce->flote,
            ];
        }

        return $demandes_flotes;
    }
}

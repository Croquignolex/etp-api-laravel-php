<?php

namespace App\Http\Controllers\API;

use App\Agent;
use App\Demande_destockage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Support\Facades\Validator;
use App\Events\NotificationsEvent;
use App\Notifications\Demande_destockage as Notif_demande_destockage;
use App\Notifications\Destockage as Notif_destockage;
use App\Flote;
use App\Enums\Roles;
use App\Role;
use App\Puce;
use Illuminate\Support\Facades\Auth;

class Demande_destockage_recouvreurController extends Controller
{
    /**

     * les conditions de lecture des methodes

     */

    function __construct(){

        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte");

    }

    /**
     * //Initier une demande de destockage pour un agent
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'Numeric'],
            'id_agent' => ['required', 'Numeric'],
            'id_puce' => ['required', 'Numeric'] //sous forme de select qui affiche juste les deux puces de type ETP
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


        if (!Puce::Find($request->id_puce)) {
            return response()->json(
                [
                    'message' => "Cette puce n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        if (!Agent::Find($request->id_agent)) {
            return response()->json(
                [
                    'message' => "Cet Agent n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //recuperer l'utilisateur connecté (c'est lui le recouvreur)
        $add_by = Auth::user();

        //recuperer l'agent concerné
        $agent = Agent::find($request->id_agent);

		$user = User::find($agent->id_user);

        $id_user = $user->id;
        $add_by = $add_by->id;
        $reference = null;
        $montant = $request->montant;
        $statut = \App\Enums\Statut::EN_ATTENTE;
        $destination = null;
        //recuperer l'id de puce de l'agent
        $id_puce = $request->id_puce;

        // Nouvelle demande de destockage
        $demande_destockage = new Demande_destockage([
            'id_user' => $id_user,
            'add_by' => $add_by,
            'reference' => $reference,
            'montant' => $montant,
            'reste' => $montant,
            'statut' => $statut,
            'puce_destination' => $destination,
            'puce_source' => $id_puce
        ]);

        // creation de La demande
        if ($demande_destockage->save()) {

            //Broadcast Notification
            $role = Role::where('name', Roles::RECOUVREUR)->first();  
            $role2 = Role::where('name', Roles::GESTION_FLOTTE)->first();  
            $event = new NotificationsEvent($role->id, ['message' => 'Nouvelle demande de destockage']);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
                //notifier les RZ et les GF
                foreach ($users as $user) {
                    
                    if ($user->hasRole([$role->name]) || $user->hasRole([$role2->name])) {
                        
                        $user->notify(new Notif_demande_destockage([
                            'data' => $demande_destockage,
                            'message' => "Nouvelle demande de Destockage"                    
                        ]));
                    }
                }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Demande de destockage créée',
                    'status' => true,
                    'data' => ['demande' => $demande_destockage]
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
     * ////lister toutes mes demandes de destockage peu importe le statut
     */
    public function list_all_status()
    {
        //On recupere les 'demande de destockage'
//        $demandes_destockage = Demande_destockage::where('add_by', Auth::user()->id)->get();
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

        return response()->json(
			[
				'message' => '',
				'status' => true,
				'data' => ['demandes' => $demandes_destockages]
			]
		);
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
            foreach ($users as $user) {
                
                if ($user->hasRole([$role->name])) {
                    
                    $user->notify(new Notif_demande_destockage([
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
                    'message' => ['error'=>$validator->errors()],
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
            foreach ($users as $user) {
                
                if ($user->hasRole([$role->name])) {
                    
                    $user->notify(new Notif_demande_destockage([
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
     * //reponse une demande de destockage
     */
    public function reponse(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric']
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

        $demande_destockage = Demande_destockage::find($id);
        $reste = $demande_destockage->reste;

        if($reste < $request->montant) {
            return response()->json(
                [
                    'message' => "Vous ne pouvez pas servir au delas de la demande",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        $demande_destockage->reste = $reste - $request->montant;

        //On change le statut de la demande de flotte
        if ($demande_destockage->reste == 0) $demande_destockage->statut = \App\Enums\Statut::EFFECTUER ;
        else $demande_destockage->statut = \App\Enums\Statut::EN_COURS ;

        // update de La demande
        if ($demande_destockage->save()) {

            //Broadcast Notification
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();    
            $event = new NotificationsEvent($role->id, ['message' => 'Une demande traitée']);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {
                
                if ($user->hasRole([$role->name])) {
                    
                    $user->notify(new Notif_destockage([
                        'data' => $demande_destockage,
                        'message' => "Une demande en cours de traitement"                    
                    ]));
                }
            }
            //On recupere les 'demande de destockage'
//            $demandes_destockage = Demande_destockage::where('add_by', Auth::user()->id)->get();
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

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demandes' => $demandes_destockages]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors traitelent de la demande',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }
}

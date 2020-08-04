<?php

namespace App\Http\Controllers\API;

use App\Agent;
use App\Demande_destockage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Support\Facades\Validator;
use App\Flote;
use App\Enums\Roles;
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
        $demandes_destockage = Demande_destockage::where('add_by', Auth::user()->id)->get();
		
		$demandes_destockages = [];
		
		foreach($demandes_destockage as $demande_destockage) {

            //recuperer l'utilisateur concerné
                $user = $demande_destockage->user;

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->first();

            $demandes_destockages[] = ['demande' => $demande_destockage, 'agent' => $agent, 'user' => $user, 'puce' => $demande_destockage->puce];
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
     * //Annuler une demande de flotte
     */
    public function annuler(Request $request, $id)
    {
		$demande_destockageDB = Demande_destockage::find($id);
		$demande_destockageDB->statut = \App\Enums\Statut::ANNULE;
		
        // creation de La demande
        if ($demande_destockageDB->save()) {
			//On recupere les 'demande de flotte'
			$demandes_destockage = Demande_destockage::where('add_by', Auth::user()->id)->get();  
			
			$demandes_destockages = [];

			foreach($demandes_destockage as $demande_destockage) {
				//recuperer l'utilisateur concerné
				$user = $demande_destockage->user;

				//recuperer l'agent concerné
				$agent = Agent::where('id_user', $user->id)->first();

				$demandes_destockages[] = ['demande' => $demande_destockage, 'agent' => $agent, 'user' => $user, 'puce' => $demande_destockage->puce]; 
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
     * //modifier une demande de Flotte
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
}

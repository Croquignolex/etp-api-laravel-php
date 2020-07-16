<?php

namespace App\Http\Controllers\API;

use App\Agent;
use App\Demande_flote;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Enums\Roles;
use App\User;
use App\Enums\Roles;
use Illuminate\Support\Facades\Validator;
use App\Flote;
use App\Puce;
use Illuminate\Support\Facades\Auth;

class Demande_flote_recouvreurController extends Controller
{
    

    /**

     * les conditions de lecture des methodes

     */

    function __construct(){

        $superviseur = Roles::SUPERVISEUR;
        $recouvreur = Roles::RECOUVREUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte");

    }



    /**
     * //Initier une demande de Flotte
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'montant' => ['required', 'Numeric'],
            'id_agent' => ['required', 'Numeric'],
            'id_puce' => ['required', 'Numeric'] 
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

        if (!Agent::Find($request->id_agent)) { 
            return response()->json(
                [
                    'message' => "Cet Agent n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );            
        }

        if (!Puce::Find($request->id_puce)) { 
            return response()->json(
                [
                    'message' => "Cette Puce n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );            
        }

        //recuperer l'utilisateur connecté (c'est lui l'agent)
        $add_by = Auth::user();

        //recuperer l'agent concerné
        $agent = Agent::Find($request->id_agent);

        $user = User::find($agent->id_user);

        // Récupérer les données validées
             
        $id_user = $user->id;
        $add_by = $add_by->id;
        $reference = null;
        $montant = $request->montant;
        $statut = \App\Enums\Statut::EN_ATTENTE;
        $source = null;
        //recuperer l'id de puce de l'agent
        $id_puce = $request->id_puce;

        // Nouvelle demande de flotte
        $demande_flote = new Demande_flote([
            'id_user' => $id_user,
            'add_by' => $add_by,
            'reference' => $reference,
            'montant' => $montant,
            'reste' => $montant,
            'statut' => $statut,
            'id_puce' => $id_puce,
            'source' => $source
        ]);


        // creation de La demande
        if ($demande_flote->save()) {

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Demande de Flote créée',
                    'status' => true,
                    'data' => ['demande_flote' => $demande_flote]
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
     * //lister toutes les demandes de flotes 
     */
    public function list_all_status_all_user()
    {


        //On recupere les 'demande de flotte'
        $demandes_flote = Demande_flote::get();  
        
        if ($demandes_flote->count() == 0) {
            return response()->json(
                [
                    'message' => 'aucune demande trouvée',
                    'status' => false,
                    'data' => null
                ]
            );
        }

        foreach($demandes_flote as $demande_flote) {

            //recuperer l'utilisateur concerné
                $user = User::Find($demande_flote->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();

            //recuperer la flotte concerné
                $flote = Flote::Find($demande_flote->user_source);

            //recuperer la puce de l'agent
            $puce = Puce::where('id_flotte', $flote->id)
            ->where('id_agent', $agent->id)
            ->First();

            $demandes_flotes[] = ['demande_flote' => $demande_flote, 'user' => $user, 'agent' => $agent, 'flote' => $flote, 'puce' => $puce,];

        }


        if (!empty($demandes_flote)) {

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demandes_flotes' => $demandes_flotes]
                ]
            );
            
         }else{
            return response()->json(
                [
                    'message' => 'pas de dmande de flote à lister',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }


    /**
     * //lister les demandes de flotes non traitées
     */
    public function list_all()
    {
        
        //On recupere les 'demande de flotte'
        $demandes_flote = Demande_flote::where('statut', \App\Enums\Statut::EN_ATTENTE)
        ->get();  
        
        if ($demandes_flote->count() == 0) {
            return response()->json(
                [
                    'message' => 'aucune demande trouvée',
                    'status' => true,
                    'data' => null
                ]
            );
        }
        
        foreach($demandes_flote as $demande_flote) {
            
            //recuperer l'utilisateur concerné
                $user = User::Find($demande_flote->id_user);
                
            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();
                
            //recuperer la puce de l'agent
            $puce = Puce::find($demande_flote->id_puce);
            $demandes_flotes[] = ['demande_flote' => $demande_flote, 'user' => $user, 'agent' => $agent, 'puce' => $puce,];

        }

        
        if (!empty($demandes_flote)) {

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demandes_flotes' => $demandes_flotes]
                ]
            );
            
         }else{
            return response()->json(
                [
                    'message' => 'pas de dmande de flote à lister',
                    'status' => true,
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
        $demandes_flote = Demande_flote::where('add_by', Auth::user()->id)
        ->get();  
        
        /*if ($demandes_flote->count() == 0) {
            return response()->json(
                [
                    'message' => 'aucune demande trouvée',
                    'status' => false,
                    'data' => null
                ]
            );
        }*/
		
		$demandes_flotes = [];

        foreach($demandes_flote as $demande_flote) {

            //recuperer l'utilisateur concerné
                $user = User::Find($demande_flote->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();

            $demandes_flotes[] = ['demande_flote' => $demande_flote, 'agent' => $agent, 'user' => $user, 'puce' => $demande_flote->puce]; 
        }


        //if (!empty($demandes_flote)) {

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demandes_flotes' => $demandes_flotes]
                ]
            );
            
        // }else{
            /*return response()->json(
                [
                    'message' => 'pas de dmande de flote à lister',
                    'status' => false,
                    'data' => null
                ]
            );*/
        // }
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

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demande_flote' => $demande_flote, 'demandeur' => $user, 'agent' => $agent, 'user' => $user, 'puce' => $demande_flote->puce]
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
     * //lister mes demandes de flotes en attente
     */
    public function list()
    { 
        //On recupere mes 'demande de flotte'
        $demandes_flote = Demande_flote::where('statut', \App\Enums\Statut::EN_ATTENTE)
        ->where('add_by', Auth::user()->id)
        ->get();


        if ($demandes_flote->count() == 0) {
            return response()->json(
                [
                    'message' => 'aucune demande trouvée',
                    'status' => false,
                    'data' => null
                ]
            );
        }

        foreach($demandes_flote as $demande_flote) {

            //recuperer l'utilisateur concerné
                $user = User::Find($demande_flote->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();

            //recuperer la flotte concerné
                $flote = Flote::Find($demande_flote->user_source);

            //recuperer la puce de l'agent
            $puce = Puce::where('id_flotte', $flote->id)
            ->where('id_agent', $agent->id)
            ->First();

            $demandes_flotes[] = ['demande_flote' => $demande_flote, 'user' => $user, 'agent' => $agent, 'flote' => $flote, 'puce' => $puce,];

        }


        if (!empty($demandes_flote)) {

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demandes_flotes' => $demandes_flotes]
                ]
            );
            
         }else{
            return response()->json(
                [
                    'message' => 'pas de dmande de flote à lister',
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
			$demandes_flote = Demande_flote::where('add_by', Auth::user()->id)->get();  
			
			$demandes_flotes = [];

			foreach($demandes_flote as $demande_flote) {
				//recuperer l'utilisateur concerné
				$user = $demande_flote->user;

				//recuperer l'agent concerné
				$agent = Agent::where('id_user', $user->id)->first();

				$demandes_flotes[] = ['demande_flote' => $demande_flote, 'demandeur' => $user, 'agent' => $agent, 'user' => $user, 'puce' => $demande_flote->puce]; 
			}
		
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Demande de Flote annulée',
                    'status' => true,
                    'data' => ['demandes_flotes' => $demandes_flotes]
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
			'id_agent' => ['required', 'Numeric'],
            'id_puce' => ['required', 'Numeric'] //sous forme de select qui affiche juste les deux puces MTN et ORANGE créé par seed
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
          
		$demande_flote = Demande_flote::find($id);
		$demande_flote->montant = $request->montant;
		$demande_flote->id_puce = $request->id_puce;
		$agent = Agent::find($request->id_agent);
		$demande_flote->id_user = $agent->id_user;

        // update de La demande
        if ($demande_flote->save()) {
			$user = $demande_flote->user;
			$agent = Agent::where('id_user', $user->id)->first(); 
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demande_flote' => $demande_flote, 'demandeur' => $user, 'agent' => $agent, 'user' => $user, 'puce' => $demande_flote->puce]
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

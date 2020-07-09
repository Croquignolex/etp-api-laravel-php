<?php

namespace App\Http\Controllers\API;

use App\Agent;
use App\Demande_destockage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Support\Facades\Validator;
use App\Flote;
use App\Puce;
use Illuminate\Support\Facades\Auth;

class Demande_destockage_recouvreurController extends Controller
{
    
    /**

     * les conditions de lecture des methodes

     */

    function __construct(){
        $this->middleware('permission:Recouvreur|Superviseur|Gestionnaire_flotte');
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
        $recouvreur = Auth::user();

        //recuperer l'agent concerné
        $agent = Agent::Find($request->id_agent);

        //recuperer la Puce qui va recevoir la flotte
        $puce_destination = Puce::Find($request->id_puce);

        //recuperer la Puce qui va envoyer la flotte
        $puce_envoi = Puce::where('id_flotte', $puce_destination->id_flotte)
            ->where('id_agent', $request->id_agent)
            ->First();

        


        // Récupérer les données validées
             
        $user = User::Find($agent->id_user);
        $add_by = $recouvreur->id;
        $reference = null;
        $montant = $request->montant;
        $statut = \App\Enums\Statut::EN_ATTENTE;
        $puce_source = $puce_envoi->id;



        // Nouvelle demande de destockage
        $demande_destockage = new Demande_destockage([
            'id_user' => $user->id,
            'add_by' => $add_by,
            'reference' => $reference,
            'montant' => $montant,
            'reste' => $montant,
            'statut' => $statut,
            'puce_destination' => $request->id_puce,
            'puce_source' => $puce_source
        ]);

        // creation de La demande
        if ($demande_destockage->save()) {

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Demande de destockage créée',
                    'status' => true,
                    'data' => ['demande_destockage' => $demande_destockage, 'user' => $user, 'agent' => $agent, 'puce_agent' => $puce_envoi]
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
        $demandes_destockage = Demande_destockage::where('add_by', Auth::user()->id)
        ->get();  
        
        if ($demandes_destockage->count() == 0) {
            return response()->json(
                [
                    'message' => 'aucune demande trouvée',
                    'status' => false,
                    'data' => null
                ]
            );
        }

        foreach($demandes_destockage as $demande_destockage) {

            //recuperer l'utilisateur concerné
                $user = User::Find($demande_destockage->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();

            //recuperer la puce de l'agent
                $puce_receptrice = Puce::Find($demande_destockage->puce_destination);

            //recuperer la flotte concerné
                $flote = Flote::Find($puce_receptrice->id_flotte);

            $demandes_destockages[] = ['demande_destockage' => $demande_destockage, 'user' => $user, 'agent' => $agent, 'flote' => $flote, 'puce_receptrice' => $puce_receptrice,];

        }


        if (!empty($demandes_destockages)) {

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demandes_flotes' => $demandes_destockages]
                ]
            );
            
         }else{
            return response()->json(
                [
                    'message' => 'pas de dmande de destockage à lister',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }


    /**
     * ////lister toutes mes demandes de destockage en attente
     */
    public function list_all()
    {


        //On recupere les 'demande de destockage'
        $demandes_destockage = Demande_destockage::where('add_by', Auth::user()->id)
        ->where('statut', \App\Enums\Statut::EN_ATTENTE)
        ->get();  
        
        if ($demandes_destockage->count() == 0) {
            return response()->json(
                [
                    'message' => 'aucune demande trouvée',
                    'status' => false,
                    'data' => null
                ]
            );
        }

        foreach($demandes_destockage as $demande_destockage) {

            //recuperer l'utilisateur concerné
                $user = User::Find($demande_destockage->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();

            //recuperer la puce de l'agent
                $puce_receptrice = Puce::Find($demande_destockage->puce_destination);

            //recuperer la flotte concerné
                $flote = Flote::Find($puce_receptrice->id_flotte);

            $demandes_destockages[] = ['demande_destockage' => $demande_destockage, 'user' => $user, 'agent' => $agent, 'flote' => $flote, 'puce_receptrice' => $puce_receptrice,];

        }


        if (!empty($demandes_destockages)) {

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demandes_flotes' => $demandes_destockages]
                ]
            );
            
         }else{
            return response()->json(
                [
                    'message' => 'pas de dmande de destockage à lister',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }


    /**
     * ////lister toutes les demandes de destockage en attente
     */
    public function list()
    {


        //On recupere les 'demande de destockage'
        $demandes_destockage = Demande_destockage::where('statut', \App\Enums\Statut::EN_ATTENTE)
        ->get();  
        
        if ($demandes_destockage->count() == 0) {
            return response()->json(
                [
                    'message' => 'aucune demande trouvée',
                    'status' => false,
                    'data' => null
                ]
            );
        }

        foreach($demandes_destockage as $demande_destockage) {

            //recuperer l'utilisateur concerné
                $user = User::Find($demande_destockage->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();

            //recuperer la puce de l'agent
                $puce_receptrice = Puce::Find($demande_destockage->puce_destination);

            //recuperer la flotte concerné
                $flote = Flote::Find($puce_receptrice->id_flotte);

            $demandes_destockages[] = ['demande_destockage' => $demande_destockage, 'user' => $user, 'agent' => $agent, 'flote' => $flote, 'puce_receptrice' => $puce_receptrice,];

        }


        if (!empty($demandes_destockages)) {

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demandes_flotes' => $demandes_destockages]
                ]
            );
            
         }else{
            return response()->json(
                [
                    'message' => 'pas de dmande de destockage à lister',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }


    /**
     * //////lister les demandes de destockage peu importe le statut
     */
    public function list_all_status_all_user()
    {


        //On recupere les 'demande de destockage'
        $demandes_destockage = Demande_destockage::get();  
        
        if ($demandes_destockage->count() == 0) {
            return response()->json(
                [
                    'message' => 'aucune demande trouvée',
                    'status' => false,
                    'data' => null
                ]
            );
        }

        foreach($demandes_destockage as $demande_destockage) {

            //recuperer l'utilisateur concerné
                $user = User::Find($demande_destockage->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();

            //recuperer la puce de l'agent
                $puce_receptrice = Puce::Find($demande_destockage->puce_destination);

            //recuperer la flotte concerné
                $flote = Flote::Find($puce_receptrice->id_flotte);

            $demandes_destockages[] = ['demande_destockage' => $demande_destockage, 'user' => $user, 'agent' => $agent, 'flote' => $flote, 'puce_receptrice' => $puce_receptrice,];

        }


        if (!empty($demandes_destockages)) {

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demandes_flotes' => $demandes_destockages]
                ]
            );
            
         }else{
            return response()->json(
                [
                    'message' => 'pas de dmande de destockage à lister',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }



}

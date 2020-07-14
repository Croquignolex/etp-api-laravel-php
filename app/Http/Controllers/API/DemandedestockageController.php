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


class DemandedestockageController extends Controller
{


    /**

     * les conditions de lecture des methodes

     */

    function __construct(){

        $this->middleware('permission:Recouvreur|Agent|Superviseur|Gestionnaire de flotte');

    }

    
    /**
     * //Initier une demande de destockage
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'montant' => ['required', 'Numeric'],
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

        if (!Puce::Find($request->id_puce)) { 
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

        //recuperer l'agent concerné
        $agent = Agent::where('id_user', $user->id)->First();

        //recuperer la Puce qui va recevoir la flotte
        $puce_destination = Puce::Find($request->id_puce);

        //recuperer la Puce qui va envoyer la flotte
        $puce_source = Puce::where('id_flotte', $puce_destination->id_flotte)
            ->where('id_agent', $agent->id)
            ->First();

        // Récupérer les données validées
             
        $id_user = $user->id;
        $add_by = $user->id;
        $reference = null;
        $montant = $request->montant;
        $statut = \App\Enums\Statut::EN_ATTENTE;
        $puce_source = $puce_source->id;



        // Nouvelle demande de destockage
        $demande_destockage = new Demande_destockage([
            'id_user' => $id_user,
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
                    'data' => ['demande_destockage' => $demande_destockage]
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
     * //lister mes demandes de destockage peu importe le statut
     */
    public function list_all_status()
    {


        //On recupere les 'demande de destockage'
        $demandes_destockage = Demande_destockage::where('id_user', Auth::user()->id)
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
     * //Lister mes demandes de destockage en attente
     */
    public function list()
    {


        //On recupere les 'demande de destockage'
        $demandes_destockage = Demande_destockage::where('id_user', Auth::user()->id)
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

            //recuperer la puce de ETP qui a recu la flote
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
     * //Details d'une demande de destockage
     */
    public function show($id)
    {
        //on recherche la demande de destockage en question
        $demande_destockage = Demande_destockage::find($id);

        //Envoie des information
        if($demande_destockage != null){

            //recuperer l'utilisateur concerné
                $user = User::Find($demande_destockage->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();

            //recuperer la puce de ETP qui a recu la flote
            $puce_receptrice = Puce::Find($demande_destockage->puce_destination);

            //recuperer la flotte concerné
                $flote = Flote::Find($puce_receptrice->id_flotte);

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demande_destockage' => $demande_destockage, 'flote' => $flote, 'agent' => $agent, 'user' => $user, 'puce_receptrice' => $puce_receptrice,]
                ]
            );

        }else{

            return response()->json(
                [
                    'message' => 'cette demande flote n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }


}

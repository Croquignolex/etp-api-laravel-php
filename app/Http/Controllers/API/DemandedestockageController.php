<?php

namespace App\Http\Controllers\API;

use App\Agent;
use App\Demande_destockage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\User;
use App\Type_transaction;
use Illuminate\Support\Facades\Validator;
use App\Flote;
use App\Puce;
use App\Transaction;
use Illuminate\Support\Facades\Auth;


class DemandedestockageController extends Controller
{


    /**

     * les conditions de lecture des methodes

     */

    function __construct(){

        $this->middleware('permission:Agent|Superviseur|Gestionnaire_flotte');

    }

    
    /**
     * //Initier une demande de destockage
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'montant' => ['required', 'Numeric'],
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

        //recuperer l'utilisateur connecté (c'est lui l'agent)
        $user = Auth::user();

        //recuperer l'agent concerné
        $agent = Agent::where('id_user', $user->id)->First();

        //recuperer la Puce qui va recevoir la flotte
        $puce_destination = Puce::Find($request->id_puce);

        //recuperer la Puce qui va envoyer la flotte
        $puce_envoi = Puce::where('id_flotte', $puce_destination->id_flotte)
            ->where('id_agent', $agent->id)
            ->First();

        


        // Récupérer les données validées
             
        $id_user = $user->id;
        $add_by = $user->id;
        $reference = null;
        $montant = $request->montant;
        $statut = \App\Enums\Statut::EN_ATTENTE;
        $puce_source = $puce_envoi->id;



        // Nouvelle demande de destockage
        $demande_destockage = new Demande_destockage([
            'id_user' => $id_user,
            'add_by' => $add_by,
            'reference' => $reference,
            'montant' => $montant,
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

}

<?php

namespace App\Http\Controllers\API;

use App\Agent;
use App\Demande_flote;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\User;
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
        $this->middleware('permission:Recouvreur|Superviseur|Gestionnaire_flotte');
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
            'id_flote' => ['required', 'Numeric'] 
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

        if (!Flote::Find($request->id_flote)) { 
            return response()->json(
                [
                    'message' => "Cette flotte n'existe pas",
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

        //recuperer la Puce correspondante
        $flote = Flote::Find($request->id_flote);

        


        // Récupérer les données validées
             
        $id_user = $user->id;
        $add_by = $add_by->id;
        $reference = null;
        $montant = $request->montant;
        $statut = \App\Enums\Statut::EN_ATTENTE;
        $user_source = $request->id_flote;
        $destination = $user->id;


        // Nouvelle demande de flotte
        $demande_flote = new Demande_flote([
            'id_user' => $id_user,
            'add_by' => $add_by,
            'reference' => $reference,
            'montant' => $montant,
            'statut' => $statut,
            'destination' => $destination,
            'user_source' => $user_source
        ]);

        //dd($demande_flote );

        // creation de La demande
        if ($demande_flote->save()) {

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Demande de Flote créée',
                    'status' => true,
                    'data' => ['demande_flote' => $demande_flote, 'user' => $user, 'agent' => $agent, 'flote' => $flote]
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
     * //lister mes demandes de flotes peu importe le statut
     */
    public function list_all_status()
    {


        //On recupere les 'demande de flotte'
        $demandes_flote = Demande_flote::where('add_by', Auth::user()->id)
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


}

<?php

namespace App\Http\Controllers\API;

use App\Agent;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

use App\User;
use App\Type_transaction;
use Illuminate\Support\Facades\Validator;
use App\Flote;
use App\Transaction;
use Illuminate\Support\Facades\Auth;

class DemandeflotteController extends Controller
{


    /**

     * les conditions de lecture des methodes

     */

    function __construct(){
        $this->middleware('permission:Agent|Superviseur|Gestionnaire_flotte');
    }



    /**
     * //Initier une demande de Flotte
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'montant' => ['required', 'Numeric'],
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

        //recuperer l'utilisateur connecté
        $user = Auth::user();

        //recuperer l'agent concerné
        $agent = Agent::where('id_user', $user->id)->First();

        //recuperer la flotte concerné
        $flote = Flote::Find($request->id_flote);

        //On recupere le type de la transaction
        $type_transaction = Type_transaction::where('nom', \App\Enums\Transations::DEMANDE_FLOTTE)->First();        


        // Récupérer les données validées
             
        $id_user = $user->id;
        $id_transaction = null;
        $id_versement = null;
        $id_type_transaction = $type_transaction->id;
        $id_flote = $request->id_flote;
        $montant = $request->montant;
        $reste = null;
        $statut = \App\Enums\Statut::EN_ATTENTE;
        $user_destination = $user->id;
        $user_source = null;


        // Nouvelle demande de flotte
        $transaction = new Transaction([
            'id_user' => $id_user,
            'id_transaction' => $id_transaction,
            'id_versement' => $id_versement,
            'id_type_transaction' => $id_type_transaction,
            'id_flote' => $id_flote,
            'montant' => $montant,
            'reste' => $reste,
            'statut' => $statut,
            'user_destination' => $user_destination,
            'user_source' => $user_source
        ]);

        // creation de La flote
        if ($transaction->save()) {

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Demande de Flote créée',
                    'status' => true,
                    'data' => ['demande_flote' => $transaction, 'user' => $user, 'agent' => $agent, 'flote' => $flote]
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
     * //lister les demandes de flotes
     */
    public function list_all()
    {

        //On recupere le type de la transaction 'demande de flotte'
        $type_transaction = Type_transaction::where('nom', \App\Enums\Transations::DEMANDE_FLOTTE)->First();

        //On recupere les 'demande de flotte'
        $demandes_flote = Transaction::where('id_type_transaction', $type_transaction->id)
        ->where('statut', \App\Enums\Statut::EN_ATTENTE)
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
                $flote = Flote::Find($demande_flote->id_flote);

            $demandes_flotes[] = ['demande_flote' => $demande_flote, 'user' => $user, 'agent' => $agent, 'flote' => $flote,];

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
     * //lister mes demandes de flotes
     */
    public function list()
    {

        //On recupere le type de la transaction 'demande de flotte'
        $type_transaction = Type_transaction::where('nom', \App\Enums\Transations::DEMANDE_FLOTTE)->First();

        //On recupere mes 'demande de flotte'
        $demandes_flote = Transaction::where('id_type_transaction', $type_transaction->id)
        ->where('statut', \App\Enums\Statut::EN_ATTENTE)
        ->where('id_user', Auth::user()->id)
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
                $flote = Flote::Find($demande_flote->id_flote);

            $demandes_flotes[] = ['demande_flote' => $demande_flote, 'user' => $user, 'agent' => $agent, 'flote' => $flote,];

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
     * //details d'une demande de flote'
     */
    public function show($id)
    {
        //on recherche la demande de flote en question
        $demande_flote = Transaction::find($id);

        //Envoie des information
        if($demande_flote != null){

            if ($demande_flote->statut != \App\Enums\Statut::EN_ATTENTE) {
                return response()->json(
                    [
                        'message' => 'cette demande flote a déjà été traitée',
                        'status' => false,
                        'data' => null
                    ]
                );
            }
            
            //recuperer l'utilisateur concerné
                $user = User::Find($demande_flote->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();

            //recuperer la flotte concerné
                $flote = Flote::Find($demande_flote->id_flote);

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demande_flote' => $demande_flote, 'flote' => $flote, 'agent' => $agent, 'user' => $user,]
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
}

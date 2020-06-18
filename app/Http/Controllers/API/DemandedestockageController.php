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


class DemandedestockageController extends Controller
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
        $type_transaction = Type_transaction::where('nom', \App\Enums\Transations::DEMANDE_DESTOCK)->First();        


        // Récupérer les données validées
             
        $id_user = $user->id;
        $id_transaction = null;
        $id_versement = null;
        $id_type_transaction = $type_transaction->id;
        $id_flote = $request->id_flote;
        $montant = $request->montant;
        $reste = null;
        $statut = \App\Enums\Statut::EN_ATTENTE;
        $user_destination = null;
        $user_source = $user->id;


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
                    'message' => 'Demande de destokage créée',
                    'status' => true,
                    'data' => ['demande_dest' => $transaction, 'user' => $user, 'agent' => $agent, 'flote' => $flote]
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
     * //lister les demandes de destockage
     */
    public function list_all()
    {

        //On recupere le type de la transaction 'demande de destockage'
        $type_transaction = Type_transaction::where('nom', \App\Enums\Transations::DEMANDE_DESTOCK)->First();

        //On recupere les 'demande de destockage'
        $demandes_dest = Transaction::where('id_type_transaction', $type_transaction->id)
        ->where('statut', \App\Enums\Statut::EN_ATTENTE)
        ->get();  
        
        if ($demandes_dest->count() == 0) {
            return response()->json(
                [
                    'message' => 'aucune demande trouvée',
                    'status' => false,
                    'data' => null
                ]
            );
        }

        foreach($demandes_dest as $demande_dest) {

            //recuperer l'utilisateur concerné
                $user = User::Find($demande_dest->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();

            //recuperer la flotte concerné
                $flote = Flote::Find($demande_dest->id_flote);

            $demandes_destock[] = ['demande_dest' => $demande_dest, 'user' => $user, 'agent' => $agent, 'flote' => $flote,];

        }


        if (!empty($demandes_destock)) {

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demandes_destock' => $demandes_destock]
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
     * //lister les demandes de destockage
     */
    public function list()
    {

        //On recupere le type de la transaction 'demande de destockage'
        $type_transaction = Type_transaction::where('nom', \App\Enums\Transations::DEMANDE_DESTOCK)->First();

        //On recupere les 'demande de destockage'
        $demandes_dest = Transaction::where('id_type_transaction', $type_transaction->id)
        ->where('statut', \App\Enums\Statut::EN_ATTENTE)
        ->where('id_user', Auth::user()->id)
        ->get();  
        
        if ($demandes_dest->count() == 0) {
            return response()->json(
                [
                    'message' => 'aucune demande trouvée',
                    'status' => false,
                    'data' => null
                ]
            );
        }

        foreach($demandes_dest as $demande_dest) {

            //recuperer l'utilisateur concerné
                $user = User::Find($demande_dest->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();

            //recuperer la flotte concerné
                $flote = Flote::Find($demande_dest->id_flote);

            $demandes_destock[] = ['demande_dest' => $demande_dest, 'user' => $user, 'agent' => $agent, 'flote' => $flote,];

        }


        if (!empty($demandes_destock)) {

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demandes_destock' => $demandes_destock]
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
     * //details d'une demande de destockage'
     */
    public function show($id)
    {
        //on recherche la demande de flote en question
        $demande_destock = Transaction::find($id);

        //Envoie des information
        if($demande_destock != null){

            if ($demande_destock->statut != \App\Enums\Statut::EN_ATTENTE) {
                return response()->json(
                    [
                        'message' => 'cette demande flote a déjà été traitée',
                        'status' => false,
                        'data' => null
                    ]
                );
            }            
            
            //recuperer l'utilisateur concerné
                $user = User::Find($demande_destock->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->First();

            //recuperer la flotte concerné
                $flote = Flote::Find($demande_destock->id_flote);

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['demande_destock' => $demande_destock, 'flote' => $flote, 'agent' => $agent, 'user' => $user,]
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
}

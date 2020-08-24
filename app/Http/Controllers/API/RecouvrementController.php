<?php

namespace App\Http\Controllers\API;

use App\Enums\Statut;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Agent;
use App\Approvisionnement;
use App\Demande_flote;
use App\User;
use App\Enums\Roles;
use Illuminate\Support\Facades\Validator;
use App\Flote;
use App\Type_puce;
use App\Puce;
use App\Caisse;
use App\Recouvrement;
use App\Http\Resources\Recouvrement as RecouvrementResource;
use App\Retour_flote;
use Illuminate\Support\Facades\Auth;

class RecouvrementController extends Controller
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

    Public function store(Request $request) {

        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'Numeric'],
            'id_flottage' => ['required', 'Numeric'],
            'recu' => ['required', 'file', 'max:10000']
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


        //On verifi si le flottage passée existe réellement
        if (!Approvisionnement::find($request->id_flottage)) {
            return response()->json(
                [
                    'message' => "le flottage n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //On verifi que le montant n'est pas supperieur au montant demandé
        if (Approvisionnement::find($request->id_flottage)->reste < $request->montant) {
            return response()->json(
                [
                    'message' => "Vous essayez de recouvrir plus d'argent que prevu",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //enregistrer le recu
        $recu = null;
        if ($request->hasFile('recu') && $request->file('recu')->isValid()) {
            $recu = $request->recu->store('recu');
        }

        //On recupère le flottage à traiter
        $flottage = Approvisionnement::find($request->id_flottage);

        //Montant du depot
        $montant = $request->montant;

        //L'agent concerné
        $user = User::Find($flottage->demande_flote->id_user);
        $agent = Agent::Where('id_user', $user->id)->first();


        //la puce de L'agent concerné
        $puce_agent = Puce::Find($flottage->demande_flote->id_puce);

        //Caisse de l'agent concerné
        $caisse = $user->caisse->first();

        //recouvreur
        $recouvreur = Auth::user();


        // Nouveau recouvrement
        $recouvrement = new Recouvrement([
            'id_user' => $recouvreur->id,
            'id_transaction' => null,
            'id_versement' => null,
            'type_transaction' => Statut::RECOUVREMENT,
            'reference' => null,
            'montant' => $montant,
            'reste' => $montant,
            'recu' => $recu,
            'id_flottage' => $request->id_flottage,
            'statut' => Statut::EN_COURS,
            'user_destination' => $recouvreur->id,
            'user_source' => $user->id
        ]);

        //si l'enregistrement du recouvrement a lieu
        if ($recouvrement->save()) {

            ////ce que le recouvrement implique

                //On credite la caisse de l'Agent pour le remboursement de la flotte recu, ce qui implique qu'il rembource ses detes à ETP
                $caisse->solde = $caisse->solde + $montant;
                $caisse->save();

                //On recupère la puce de l'agent concerné et on debite
                $puce_agent->solde = $puce_agent->solde - $montant;
                $puce_agent->save();

                //On calcule le reste à recouvrir
                $flottage->reste = $flottage->reste - $montant;

                //On change le statut du flottage
                if ($flottage->reste == 0) {

                    $flottage->statut = \App\Enums\Statut::TERMINEE ;

                }else {

                    $flottage->statut = \App\Enums\Statut::EN_COURS ;

                }

                //Enregistrer les oppérations
                $flottage->save();

                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => "Le recouvrement c'est bien passé",
                        'status' => true,
                        'data' => ['flottage' => $flottage, 'recouvrement' => $recouvrement]
                    ]
                );



        }else {

            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors du recouvrement',
                    'status' => false,
                    'data' => null
                ]
            );

        }

    }

    /**
     * ////details d'un recouvrement
     */
    public function show($id)
    {

            //si le recouvrement n'existe pas
            if (!($recouvrement = Recouvrement::find($id))) {
                return response()->json(
                    [
                        'message' => "le recouvrement n'existe pas",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

            return new RecouvrementResource($recouvrement);


    }

    /**
     * ////lister tous les recouvrement
     */
    public function list_all()
    {
        //On recupere les recouvrement
        $recouvrements = Recouvrement::get();

        $approvisionnements = [];

        foreach($recouvrements as $recouvrement) {

            //recuperer le flottage correspondant
            $flottage = Approvisionnement::find($recouvrement->id_flottage);

            //recuperer celui qui a éffectué le recouvrement
                $user = User::find($recouvrement->id_user);

            //recuperer l'agent concerné
                $user = User::find($flottage->demande_flote->id_user);
                $agent = Agent::Where('id_user', $user->id)->first();

                $recouvreur = User::find($recouvrement->user_destination);

            //recuperer la puce de l'agent
                $puce_agent = Puce::find($flottage->demande_flote->id_puce);

            $approvisionnements[] = [
                'recouvrement' => $recouvrement,
                'flottage' => $flottage,
                'user' => $user,
                'agent' => $agent,
                'recouvreur' => $recouvreur,
//                'puce_agent' => $puce_agent
            ];

        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['recouvrements' => $approvisionnements]
            ]
        );

    }

    /**
     * ////lister les recouvrements d'un flottage
     */
    public function list_recouvrement($id)
    {
        if (!Approvisionnement::Find($id)){

            return response()->json(
                [
                    'message' => "le flottage n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }

        //On recupere les recouvrements
        $recouvrements = Recouvrement::where('id_flottage', $id)->get();


        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['recouvrements' => $recouvrements]
            ]
        );

    }

    /**
     * ////lister les recouvrements d'un RZ
     */
    public function list_recouvrement_by_rz($id)
    {
        if (!User::Find($id)){

            return response()->json(
                [
                    'message' => "le Responsable de zonne n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }

        //On recupere les recouvrements
        $recouvrements = Recouvrement::where('id_user', $id)->get();


        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['recouvrements' => $recouvrements]
            ]
        );

    }

    /**
     * ////lister les recouvrements d'un Agent precis
     */
    public function list_recouvrement_by_agent($id)
    {
        if (!User::Find($id)){

            return response()->json(
                [
                    'message' => "l'agent' n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }

        //On recupere les recouvrements
        $recouvrements = Recouvrement::where('user_source', $id)->get();


        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['recouvrements' => $recouvrements]
            ]
        );

    }
}

<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
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
use Illuminate\Support\Facades\Auth;
class FlotageController extends Controller
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
<<<<<<< .mine
        $validator = Validator::make($request->all(), [ 
            'montant' => ['required', 'numeric'], 
            'id_demande_flotte' => ['required', 'numeric'],
            'id_puce' => ['required', 'numeric']
=======
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'Numeric'],
            'id_demande_flotte' => ['required', 'Numeric'],
            'id_puce' => ['required', 'Numeric']
>>>>>>> .theirs
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


        //On verifi si la demande passée existe réellement
        if (!Demande_flote::find($request->id_demande_flotte)) {
            return response()->json(
                [
                    'message' => "la demande de flotte n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //On verifi que le montant n'est pas supperieur au montant demandé
        if (Demande_flote::find($request->id_demande_flotte)->reste < $request->montant) {
            return response()->json(
                [
                    'message' => "Vous essayez d'envoyer plus de flotte que prevu",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // On verifi que la puce passée en paramettre existe
        if (Puce::find($request->id_puce)) {

            //On recupère la puce ETP qui va faire le depot
            $puce_etp = Puce::find($request->id_puce);

            //On recupère la demande à traiter
            $demande_flotte = Demande_flote::find($request->id_demande_flotte);

            //On recupère la puce de l'Agent qui va etre approvisionné
            $puce_agent = Puce::find($demande_flotte->id_puce);

            //on recupère le typ de la puce
            $type_puce = Type_puce::find($puce_etp->type)->name;

            //On se rassure que la puce passée en paramettre est reelement l'une des puces de flottage sollicités
            if ($type_puce == \App\Enums\Statut::AGENT || $type_puce == \App\Enums\Statut::ETP || $puce_etp->id_flotte != $puce_agent->id_flotte) {
                return response()->json(
                    [
                        'message' => "cette puce n'est pas capable d'effectuer un flottagage",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

        }else {
            return response()->json(
                [
                    'message' => "la puce n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //Montant du depot
        $montant = $request->montant;

        //Caisse de l'agent concerné
        $caisse = Caisse::where('id_user', $demande_flotte->id_user)->first();

        //L'agent concerné
        $agent = Agent::where('id_user', $demande_flotte->id_user)->first();

        //La gestionnaire concernée
        $gestionnaire = Auth::user();


        // Nouveau flottage
        $flottage = new Approvisionnement([
            'id_demande_flote' => $demande_flotte->id,
            'id_user' => $gestionnaire->id,
            'reference' => null,
            'statut' => \App\Enums\Statut::TERMINEE,
            'note' => null,
            'montant' => $montant
        ]);

        //si l'enregistrement du flottage a lieu
        if ($flottage->save()) {

            ////ce que le flottage implique

                //On debite la puce de ETP
                $puce_etp->solde = $puce_etp->solde - $montant;
                $puce_etp->save();

                //On credite la puce de l'Agent
                $puce_agent->solde = $puce_agent->solde + $montant;
                $puce_agent->save();

                //On debite la caisse de l'Agent pour le paiement de la flotte envoyée, ce qui implique qu'il doit à ETP
                $caisse->solde = $caisse->solde - $montant;
                $caisse->save();

                //On calcule le reste de flotte à envoyer
                $demande_flotte->reste = $demande_flotte->reste - $montant;

                //On change le statut de la demande de flotte
                if ($demande_flotte->reste == 0) {

                    $demande_flotte->statut = \App\Enums\Statut::EFFECTUER ;

                }else {

                    $demande_flotte->statut = \App\Enums\Statut::EN_COURS ;

                }

                //Enregistrer les oppérations
                $demande_flotte->save();
				
				
				$user = $demande_flotte->user; 
				$demandeur = User::Find($demande_flotte->add_by);

                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => "Le flottage c'est bien passé",
                        'status' => true,
                        'data' => [
							'demande_flote' => $demande_flotte,
							'demandeur' => $demandeur, 
							'agent' => $agent, 
							'user' => $user, 
							'approvisionnements' => $demande_flotte->approvisionnements,
							'puce' => $demande_flotte->puce 
						]
                    ]
                );



        }else {

            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors du flottage',
                    'status' => false,
                    'data' => null
                ]
            );

        }

    }

    /**
     * ////lister tous les flottages
     */
    public function list_all()
    {

        //On recupere les Flottages
        $flottages = Approvisionnement::get();

        foreach($flottages as $flottage) {

            //recuperer la demande correspondante
            $demande = $flottage->demande_flote()->first();

            //recuperer celui qui a éffectué le flottage
                $user = User::Find($flottage->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $demande->id_user)->get();

            //recuperer la puce de l'agent
                $puce_receptrice = Puce::Find($demande->id_puce);

            $approvisionnements[] = ['approvisionnement' => $flottage,'demande' => $demande, 'user' => $user, 'agent' => $agent, 'puce_receptrice' => $puce_receptrice,];

        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $approvisionnements]
            ]
        );

    }


    /**
         * ////lister tous les flottages
         */
        public function show($id_flottage)
        {

            //On recupere le Flottage
            $flottage = Approvisionnement::find($id_flottage);

            //recuperer la demande correspondante
            $demande = $flottage->demande_flote()->first();

            //recuperer celui qui a éffectué le flottage
                $user = User::Find($flottage->id_user);

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $demande->id_user)->get();

            //recuperer la puce de l'agent
                $puce_receptrice = Puce::Find($demande->id_puce);

            $approvisionnements[] = ['approvisionnement' => $flottage,'demande' => $demande, 'user' => $user, 'agent' => $agent, 'puce_receptrice' => $puce_receptrice,];



            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['flottages' => $approvisionnements]
                ]
            );

        }



    /**
     * ////lister les flottages d'une demande
     */
    public function list_flottage($id)
    {
        if (!Demande_flote::Find($id)){

            return response()->json(
                [
                    'message' => "la demande specifiée n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }

        //On recupere les Flottage
        $flottages = Approvisionnement::where('id_demande_flote', $id)->get();


        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $flottages]
            ]
        );

    }

}

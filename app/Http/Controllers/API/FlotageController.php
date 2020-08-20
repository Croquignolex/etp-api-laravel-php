<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Puce;
use App\Agent;
use App\Caisse;
use App\Type_puce;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Demande_flote;
use App\Approvisionnement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class FlotageController extends Controller
{
    /**

     * les conditions de lecture des methodes

     */
    function __construct(){

        $agent = Roles::AGENT;
        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$agent");

    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    Public function store(Request $request) {

        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_demande_flotte' => ['required', 'numeric'],
            'id_puce' => ['required', 'numeric']
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
            if ($type_puce == Statut::AGENT || $type_puce == Statut::ETP || $puce_etp->id_flotte != $puce_agent->id_flotte) {
                return response()->json(
                    [
                        'message' => "cette puce n'est pas capable d'effectuer ce flottagage",
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
            'from' => $puce_etp->id,
            'id_user' => $gestionnaire->id,
            'reference' => null,
            'statut' => Statut::TERMINEE,
            'note' => null,
            'montant' => $montant,
            'reste' => $montant
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

                $demande_flotte->source = $puce_etp->id;

                //On change le statut de la demande de flotte
                if ($demande_flotte->reste == 0) {
                    $demande_flotte->statut = Statut::EFFECTUER ;
                }else {
                    $demande_flotte->statut = Statut::EN_COURS ;
                }

                //Enregistrer les oppérations
                $demande_flotte->save();

				$user = $demande_flotte->user;
				$demandeur = User::find($demande_flotte->add_by);

                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => "Le flottage c'est bien passé",
                        'status' => true,
                        'data' => [
							'demande' => $demande_flotte,
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
     * @param Request $request
     * @return JsonResponse
     */
    Public function flottage_express(Request $request) {

        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'Numeric'],
            'id_agent' => ['required', 'Numeric'],
            'id_puce_agent' => ['required', 'Numeric'],
            'id_puce_flottage' => ['required', 'Numeric']
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

        //On verifi si l'agent passé existe réellement
        if (!Agent::Find($request->id_agent)) {
            return response()->json(
                [
                    'message' => "Cet Agent n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //On verifi si la puce agent passée existe réellement
        if (!Puce::Find($request->id_puce_agent)) {
            return response()->json(
                [
                    'message' => "Cette Puce Agent n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //On verifi si la puce de  flottage passée existe réellement
        if (!Puce::Find($request->id_puce_flottage)) {
            return response()->json(
                [
                    'message' => "Cette Puce de flottage n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //recuperer l'agent concerné
        $agent = Agent::Find($request->id_agent);
        $user = $agent->user;

        // Récupérer les données pour la création d'une demande fictive de flotte
            $id_user = $user->id;
            $add_by = $user->id;
            $reference = null;
            $montant = $request->montant;
            $statut = Statut::EFFECTUER;
            $source = $request->id_puce_flottage;
            //recuperer l'id de puce de l'agent
            $id_puce = $request->id_puce_agent;

        // Nouvelle demande fictive de flotte
        $demande_flotte = new Demande_flote([
            'id_user' => $id_user,
            'add_by' => $add_by,
            'reference' => $reference,
            'montant' => $montant,
            'reste' => 0,
            'statut' => $statut,
            'id_puce' => $id_puce,
            'source' => $source
        ]);

        // On verifi que la puce passée en paramettre existe
        if (Puce::find($request->id_puce_flottage)) {
            //On recupère la puce de l'Agent qui va etre approvisionné
            $puce_agent = Puce::find($request->id_puce_agent);

            //On recupère la puce ETP qui va faire le depot
            $puce_etp = Puce::find($request->id_puce_flottage);

            //on recupère le typ de la puce
            $type_puce = Type_puce::find($puce_etp->type)->name;

            //On se rassure que la puce passée en paramettre est reelement l'une des puces de flottage sollicités
            if ($type_puce == Statut::AGENT || $type_puce == Statut::ETP || $puce_etp->id_flotte != $puce_agent->id_flotte) {
                return response()->json(
                    [
                        'message' => "cette puce n'est pas capable d'effectuer un flottagage",
                        'status' => false,
                        'data' => null
                    ]
                );
            }
        } else {
            return response()->json(
                [
                    'message' => "la puce n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // creation de La demande fictive de flotte
        if ($demande_flotte->save()) {

            //Montant du depot
            $montant = $request->montant;

            //Caisse de l'agent concerné
            //$caisse = Caisse::where('id_user', $demande_flotte->id_user)->first();

            //La gestionnaire concernée
            $gestionnaire = Auth::user();


            // Nouveau flottage
            $flottage = new Approvisionnement([
                'id_demande_flote' => $demande_flotte->id,
                'id_user' => $gestionnaire->id,
                'reference' => null,
                'statut' => Statut::TERMINEE,
                'note' => null,
                'montant' => $montant,
                'reste' => 0
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
                //$caisse->solde = $caisse->solde - $montant;
               //$caisse->save();

                $flottages = Approvisionnement::get();

                foreach($flottages as $flottage) {

                    //recuperer la demande correspondante
                    $demande = $flottage->demande_flote;

                    //recuperer l'agent concerné
                    $user = $demande->user;

                    //recuperer l'agent concerné
                    $agent = Agent::where('id_user', $user->id)->first();

                    // recuperer celui qui a éffectué le flottage
                    $gestionnaire = User::find($flottage->id_user);

                    //recuperer la puce de l'agent
                    $puce_receptrice = Puce::find($demande->id_puce);

                    $approvisionnements[] = [
                        'approvisionnement' => $flottage,
                        'demande' => $demande,
                        'user' => $user,
                        'agent' => $agent,
                        'gestionnaire' => $gestionnaire,
                        'puce' => $puce_receptrice
                    ];
                }

                return response()->json(
                    [
                        'message' => '',
                        'status' => true,
                        'data' => ['flottages' => $approvisionnements]
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
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la demande de flotte',
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

        $approvisionnements = [];

        foreach($flottages as $flottage) {

            //recuperer la demande correspondante
            $demande = $flottage->demande_flote;

            //recuperer l'agent concerné
                $user = $demande->user;

            //recuperer l'agent concerné
                $agent = Agent::where('id_user', $user->id)->first();

            // recuperer celui qui a éffectué le flottage
                $gestionnaire = User::find($flottage->id_user);

            //recuperer la puce de l'agent
                $puce_receptrice = Puce::find($demande->id_puce);

            $approvisionnements[] = [
                'approvisionnement' => $flottage,
                'demande' => $demande,
                'user' => $user,
                'agent' => $agent,
                'gestionnaire' => $gestionnaire,
                'puce' => $puce_receptrice
            ];
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
     * ////lister tous les flottages pour un agent
     */
    public function list_all_agent($id)
    {
        //On recupere les Flottages
        $flottages = Approvisionnement::get()->filter(function(Approvisionnement $approvisionnement) use ($id) {
            $demande_de_flotte = $approvisionnement->demande_flote;
            return ($demande_de_flotte->user->id == $id);
        });

        $approvisionnements = [];

        foreach($flottages as $flottage) {

            //recuperer la demande correspondante
            $demande = $flottage->demande_flote;

            //recuperer l'agent concerné
            $user = $demande->user;

            //recuperer l'agent concerné
            $agent = Agent::where('id_user', $user->id)->first();

            // recuperer celui qui a éffectué le flottage
            $gestionnaire = User::find($flottage->id_user);

            //recuperer la puce de l'agent
            $puce_receptrice = Puce::find($demande->id_puce);

            $approvisionnements[] = [
                'approvisionnement' => $flottage,
                'demande' => $demande,
                'user' => $user,
                'agent' => $agent,
                'gestionnaire' => $gestionnaire,
                'puce' => $puce_receptrice
            ];
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
     * ////lister tous les flottages pour responsable de zone
     */
    public function list_all_collector($id)
    {
        //On recupere les Flottages
        $flottages = Approvisionnement::get()->filter(function(Approvisionnement $approvisionnement) use ($id) {
            $demande_de_flotte = $approvisionnement->demande_flote;
            return ($demande_de_flotte->add_by == $id);
        });

        foreach($flottages as $flottage) {

            //recuperer la demande correspondante
            $demande = $flottage->demande_flote;

            //recuperer l'agent concerné
            $user = $demande->user;

            //recuperer l'agent concerné
            $agent = Agent::where('id_user', $user->id)->first();

            // recuperer celui qui a éffectué le flottage
            $gestionnaire = User::find($flottage->id_user);

            //recuperer la puce de l'agent
            $puce_receptrice = Puce::find($demande->id_puce);

            $approvisionnements[] = [
                'approvisionnement' => $flottage,
                'demande' => $demande,
                'user' => $user,
                'agent' => $agent,
                'gestionnaire' => $gestionnaire,
                'puce' => $puce_receptrice
            ];
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

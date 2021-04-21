<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Puce;
use App\Caisse;
use App\Type_puce;
use App\Flottage_Rz;
use App\Enums\Roles;
use App\Enums\Statut;
use App\FlotageAnonyme;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class FlotageAnonymeRZController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $recouvreur = Roles::RECOUVREUR;
        $this->middleware("permission:$recouvreur");
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * Creer un Flottage pour un anonyme
     */
    public function flottage_anonyme(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'nom_agent' => ['nullable', 'string'], //le nom de celui qui recoit la flotte
            'id_puce_from' => ['required', 'numeric'],
            'nro_puce_to' => ['required', 'numeric'], //le numéro de la puce qui recoit la flotte
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // On verifi que la puce d'envoie passée en paramettre existe
        if (Puce::find($request->id_puce_from)) {

            //On recupère la puce qui envoie
            $puce_from = Puce::find($request->id_puce_from);

        } else {
            return response()->json([
                'message' => "Une ou plusieurs puces entrées n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //On se rassure que le solde est suffisant
        if ($puce_from->solde < $request->montant) {
            return response()->json([
                'message' => "Le solde de la puce émetrice est insuffisant",
                'status' => false,
                'data' => null
            ]);
        }

        //on debite le solde de celui qui envoie
        $puce_from->solde = $puce_from->solde - $request->montant;
        $puce_from->save();

        //L'utilisateur qui envoie
        $user = Auth::user();

        //On credite la caisse de celui qui envoie (RZ plutot débit)
        $caisse = $user->caisse()->first();
        $caisse->solde = $caisse->solde - $request->montant;
        $caisse->save();

        // On verrifie si la puce anonyme existe dans la list des puces agents connus
       $agent_sim_type_id = Type_puce::where('name', Statut::AGENT)->get()->first()->id;
       $resource_sim_type_id = Type_puce::where('name', Statut::RESOURCE)->get()->first()->id;

        $needle_sim = Puce::where('numero', $request->nro_puce_to)->get()->first();

        if(($needle_sim !== null) && (
            ($needle_sim->type == $agent_sim_type_id) ||
            ($needle_sim->type == $resource_sim_type_id)
        )) {
            //======================================================================
            // Enregistrement du flottage agent

            // Récupérer les données pour la création d'une demande fictive de flotte

            //Montant du depot
            $montant = $request->montant;

            //on recupère la flotte de la puce qui recoit
            $flote_to = $needle_sim->flote;
            $flote_from = $puce_from->flote;

            //On verifie que c'est les puce du meme reseau
            if ($flote_to != $flote_from) {
                return response()->json([
                    'message' => "Vous devez choisir les puces du meme réseau",
                    'status' => false,
                    'data' => null
                ]);
            }

            //On se rassure que le solde est suffisant
            if ($puce_from->solde < $request->montant) {
                return response()->json([
                    'message' => "Le solde est insuffisant",
                    'status' => false,
                    'data' => null
                ]);
            }

            // Nouveau flottage
            $flottage_rz = new Flottage_Rz([
                'id_responsable_zone' => $user->id,
                'id_agent' => $needle_sim->agent->id,
                'id_sim_agent' => $needle_sim->id,
                'reference' => null,
                'statut' => Statut::EFFECTUER,
                'montant' => $request->montant,
                'reste' => 0
            ]);

            //si l'enregistrement du flottage a lieu
            if ($flottage_rz->save()) {
                ////ce que le flottage implique

                //On credite la puce de l'Agent
                $needle_sim->solde = $needle_sim->solde + $montant;
                $needle_sim->save();

                $caisse0 = Caisse::where('id_user', $needle_sim->agent->user->id)->first();
                //On debite la caisse de l'Agent pour le paiement de la flotte envoyée, ce qui implique qu'il doit à ETP
                $caisse0->solde = $caisse0->solde - $montant;
                $caisse0->save();

                return response()->json([
                    'message' => "Numéro réconnu par le system. Flottage agent dans le réseau éffectué à la place.",
                    'status' => true,
                    'data' => null
                ]);
            } else {
                // Renvoyer une erreur
                return response()->json([
                    'message' => 'Erreur lors du flottage',
                    'status' => false,
                    'data' => null
                ]);
            }
        } else {
            //=================================================================================
            // Nouveau flottage
            $flottage_anonyme = new FlotageAnonyme([
                'id_user' => $user->id,
                'id_sim_from' => $puce_from->id,
                'nro_sim_to' => $request->nro_puce_to,
                'reference' => Statut::FLOTTAGE_ANONYME_RESPONSABLE,
                'statut' => Statut::EFFECTUER,
                'nom_agent' => $request->nom_agent,
                'montant' => $request->montant
            ]);

            //si l'enregistrement du flottage a lieu
            if ($flottage_anonyme->save()) {

                $puce_envoie = Puce::find($flottage_anonyme->id_sim_from);

                // Renvoyer un message de succès
                return response()->json([
                    'message' => "Flottage anonyme effectué avec succès",
                    'status' => true,
                    'data' => [
                        'puce_emetrice' => $puce_envoie,
                        'user' => User::find($flottage_anonyme->id_user),
                        'flottage' => $flottage_anonyme
                    ]
                ]);
            } else {
                // Renvoyer une erreur
                return response()->json([
                    'message' => 'Erreur perdant le processus de flottage',
                    'status' => false,
                    'data' => null
                ]);
            }
        }
    }

    /**
     * ////lister les flottages anonyme effectués par un user precis
     */
    public function list_flottage_anonyme()
    {
        $connected_user = Auth::user();

        $anonymous = FlotageAnonyme::where('id_user', $connected_user->id)
            ->where('reference', Statut::FLOTTAGE_ANONYME_RESPONSABLE)
            ->orderBy('created_at', 'desc')->paginate(6);

        $anonymous_response =  $this->anonymousResponse($anonymous->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'flottages' => $anonymous_response,
                'hasMoreData' => $anonymous->hasMorePages(),
            ]
        ]);
    }

    // Build anonymous return data
    private function anonymousResponse($anonymous)
    {
        $returnedAnonymous = [];

        foreach($anonymous as $anonyme)
        {
            //puce de l'envoie
            $puce_envoie = Puce::find($anonyme->id_sim_from);

            $returnedAnonymous[] = [
                'puce_emetrice' => $puce_envoie,
                'user' => User::find($anonyme->id_user),
                'flottage' => $anonyme
            ];
        }

        return $returnedAnonymous;
    }
}

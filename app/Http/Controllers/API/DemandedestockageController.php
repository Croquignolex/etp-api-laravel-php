<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Puce;
use App\Agent;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Demande_destockage;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Demande_destockage as Notif_demande_destockage;

class DemandedestockageController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
	{
        $agent = Roles::AGENT;
        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$agent|$superviseur|$ges_flotte");
    }

    /**
     * Initier une demande de destockage
     */
    // AGENT
    // RESSOURCE
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_puce' => ['required', 'numeric']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        //Coherence du montant de la transaction
        $montant = $request->montant;
        if ($montant <= 0) {
            return response()->json([
                'message' => "Montant de la transaction incohérent",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérifier la puce agent
        $puce = Puce::find($request->id_puce);
        if (is_null($puce)) {
            return response()->json([
                'message' => "Cette puce n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Determine if agent or ressource
        $connected_user = Auth::user();
        $agent = $connected_user->agent->first();
        $reference = $agent->reference;

        if($reference === Statut::AGENT) {
            // Vérification de l'appartenance de la puce
            if ($puce->agent->user->id !== $connected_user->id) {
                return response()->json([
                    'message' => "Cette puce ne vous appartient pas",
                    'status' => false,
                    'data' => null
                ]);
            }
        } else {
            // Vérification que la puce est une puce ressource
            if ($puce->type_puce->name !== Statut::RESOURCE) {
                return response()->json([
                    'message' => "Cette puce n'est pas une puce ressource",
                    'status' => false,
                    'data' => null
                ]);
            }
        }

        // Nouvelle demande de destockage
        $demande_destockage = new Demande_destockage([
            'id_user' => $connected_user->id,
            'add_by' => $connected_user->id,
            'montant' => $montant,
            'reste' => $montant,
            'statut' => Statut::EN_ATTENTE,
            'puce_source' => $puce->id
        ]);
        $demande_destockage->save();

        //Database Notification
        $message = "Nouvelle demande de déstockage éffectué par " . $connected_user->name;
        $users = User::all();
        foreach ($users as $_user) {
            if ($_user->hasRole([Roles::RECOUVREUR])) {
                $_user->notify(new Notif_demande_destockage([
                    'data' => $demande_destockage,
                    'message' => $message
                ]));
            }
        }

        //recuperer l'agent concerné
        $agent = $connected_user->agent->first();

        // Renvoyer un message de succès
        return response()->json([
            'message' => 'Demande de déstockage effectué avec succès',
            'status' => true,
            'data' => [
                'demande' => $demande_destockage,
                'demandeur' => $connected_user,
                'agent' => $agent,
                'user' => $connected_user,
                'puce' => $puce,
                'operateur' => $puce->flote,
            ]
        ]);
    }

    /**
     * Lister mes demandes de destockage peu importe le statut
     */
    // AGENT
    // RESSOURCE
    public function list_all_status()
    {
        $user = Auth::user();
        $demandes_destockage = Demande_destockage::where('id_user', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'demandes' => $this->clearancesResponse($demandes_destockage->items()),
                'hasMoreData' => $demandes_destockage->hasMorePages()
            ]
        ]);
    }

    /**
     * Lister mes demandes de destockage peu importe le statut
     */
    // AGENT
    // RESSOURCE
    public function list_all_status_all()
    {
        $user = Auth::user();
        $demandes_destockage = Demande_destockage::where('id_user', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'demandes' => $this->clearancesResponse($demandes_destockage),
                'hasMoreData' => false
            ]
        ]);
    }

	/**
     * //Annuler une demande de flotte
     */
    public function annuler(Request $request, $id)
    {
        $demande_destockage = Demande_destockage::find($id);
        //si le destockage n'existe pas
        if (is_null($demande_destockage)) {
            return response()->json([
                'message' => "La demande de déstockage n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($demande_destockage->statut === Statut::ANNULE) {
            return response()->json([
                'message' => "La demande de déstockage a déjà été annulée",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($demande_destockage->statut === Statut::EFFECTUER) {
            return response()->json([
                'message' => "La demande de déstockage a déjà été confirmée",
                'status' => false,
                'data' => null
            ]);
        }

        //on approuve le flottage
        $demande_destockage->statut = Statut::ANNULE;
        $demande_destockage->save();

        return response()->json([
            'message' => "Demande de déstockage annulée avec succès",
            'status' => true,
            'data' => null
        ]);
    }

    // Build clearances return data
    private function clearancesResponse($clearances)
    {
        $demandes_destockages = [];

        foreach($clearances as $demande_destockage)
        {
            //recuperer l'utilisateur concerné
            $user = $demande_destockage->user;

            //recuperer l'agent concerné
            $agent = Agent::where('id_user', $user->id)->first();

            //recuperer le demandeur
            $demandeur = User::find($demande_destockage->add_by);

            $demandes_destockages[] = [
                'demande' => $demande_destockage,
                'demandeur' => $demandeur,
                'agent' => $agent,
                'user' => $user,
                'puce' => $demande_destockage->puce,
                'operateur' => $demande_destockage->puce->flote
            ];
        }

        return $demandes_destockages;
    }
}

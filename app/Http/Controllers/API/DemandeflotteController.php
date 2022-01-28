<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Puce;
use App\Enums\Statut;
use \App\Enums\Roles;
use App\Demande_flote;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Demande_flotte as Notif_demande_flotte;

class DemandeflotteController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct(){
        $agent = Roles::AGENT;
        $recouvreur = Roles::RECOUVREUR;
        $controlleur = Roles::CONTROLLEUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$agent|$superviseur|$ges_flotte|$controlleur");
    }

    /**
     * Initier une demande de Flotte
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

        // Nouvelle demande de flotte
        $demande_flote = new Demande_flote([
            'id_user' => $connected_user->id,
            'add_by' => $connected_user->id,
            'montant' => $montant,
            'reste' => $montant,
            'statut' => Statut::EN_ATTENTE,
			'id_puce' => $puce->id,
        ]);
        $demande_flote->save();

        //Database Notification
        $message = "Nouvelle demande de flotte éffectué par " . $connected_user->name;
        $users = User::all();
        foreach ($users as $_user) {
            if ($_user->hasRole([Roles::GESTION_FLOTTE]) || $_user->hasRole([Roles::RECOUVREUR])) {
                $_user->notify(new Notif_demande_flotte([
                    'data' => $demande_flote,
                    'message' => $message
                ]));
            }
        }

        // Renvoyer un message de succès
        return response()->json([
            'message' => 'Demande de flotte éffectuée avec succès',
            'status' => true,
            'data' => [
                'demande' => $demande_flote,
                'demandeur' => $connected_user,
                'agent' => $agent,
                'user' => $connected_user,
                'puce' => $puce,
                'operateur' => $puce->flote
            ]
        ]);
    }

    /**
     * Lister mes demandes de flotes peu importe le statut
     */
    // AGENT
    // RESSOURCE
    public function list_all_status()
    {
        $user = Auth::user();
        $demandes_flote = Demande_flote::where('id_user', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' => $this->fleetsResponse($demandes_flote->items()),
                'hasMoreData' => $demandes_flote->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister mes demandes de flotes (gestionnaire de flotte ou les admin)
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_demandes_flote_general()
    {
        $demandes_flote = Demande_flote::orderBy('created_at', 'desc')->paginate(9);

        $demandes_flotes = $this->fleetsResponse($demandes_flote->items());

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' => $demandes_flotes,
                'hasMoreData' => $demandes_flote->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister mes demandes de flotes (gestionnaire de flotte ou les admin)
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_demandes_flote_general_groupee()
    {
        $demandes_flote = Demande_flote::where('statut', Statut::EN_ATTENTE)->orderBy('created_at', 'desc')->get();

        $demandes_flotes = $this->fleetsResponse($demandes_flote);

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' => $demandes_flotes
            ]
        ]);
    }

    /**
     * lister mes demandes de flotes responsable de zone
     */
    // RESPONSABLE DE ZONE
    public function list_demandes_flote_collector()
    {
        $user = Auth::user();
        $demandes_flote = Demande_flote::where('add_by', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' =>  $this->fleetsResponse($demandes_flote->items()),
                'hasMoreData' => $demandes_flote->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister toutes mes demandes de flotes (responsable de zone)
     */
    // RESPONSABLE DE ZONE
    public function list_demandes_flote_collector_all()
    {
        $user = Auth::user();
        $demandes_flote = Demande_flote::where('add_by', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' => $this->fleetsResponse($demandes_flote),
                'hasMoreData' => false
            ]
        ]);
    }

    /**
     * Lister toutes mes demandes de flotes (agent)
     */
    // AGENT
    // RESSOURCE
    public function list_demandes_flote_agent_all()
    {
        $user = Auth::user();
        $demandes_flote = Demande_flote::where('id_user', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' => $this->fleetsResponse($demandes_flote),
                'hasMoreData' => false
            ]
        ]);
    }

    /**
     * lister toutes mes demandes de flotes (gestionnaire de flotte ou les admin)
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_demandes_flote_general_all()
    {
        $demandes_flote = Demande_flote::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'demandes' => $this->fleetsResponse($demandes_flote)
            ]
        ]);
    }

	/**
     * //Annuler une demande de flotte
     */
    public function annuler(Request $request, $id)
    {
        $demande_flotte = Demande_flote::find($id);
        //si le destockage n'existe pas
        if (is_null($demande_flotte)) {
            return response()->json([
                'message' => "La demande de flotte n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($demande_flotte->statut === Statut::ANNULE) {
            return response()->json([
                'message' => "La demande de flotte a déjà été annulée",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($demande_flotte->statut === Statut::EFFECTUER) {
            return response()->json([
                'message' => "La demande de flotte a déjà été confirmée",
                'status' => false,
                'data' => null
            ]);
        }

        //on approuve le flottage
        $demande_flotte->statut = Statut::ANNULE;
        $demande_flotte->save();

        return response()->json([
            'message' => "Demande de flotte annulée avec succès",
            'status' => true,
            'data' => null
        ]);
    }

    // Build fleets return data
    private function fleetsResponse($fleets)
    {
        $demandes_flotes = [];

        foreach($fleets as $demande_flote) {
            $user = $demande_flote->user;
            $agent = $user->agent->first();
            $demandeur = $demande_flote->creator;

            $demandes_flotes[] = [
                'demande' => $demande_flote,
                'demandeur' => $demandeur,
                'agent' => $agent,
                'user' => $user,
                'puce' => $demande_flote->puce,
                'operateur' => $demande_flote->puce->flote,
            ];
        }

        return $demandes_flotes;
    }
}

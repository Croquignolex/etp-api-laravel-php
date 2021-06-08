<?php

namespace App\Http\Controllers\API;

use App\Treasury;
use App\Enums\Roles;
use App\Enums\Transations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class TreasuryController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $ges_flotte = Roles::GESTION_FLOTTE;
        $superviseur = Roles::SUPERVISEUR;
        $responsable = Roles::RECOUVREUR;
        $this->middleware("permission:$ges_flotte|$superviseur|$responsable");
    }

    /**
     * Creer un Encaissement
     */
    // GESTIONNAIRE DE FLOTTE
    // RESPONSABLE DE ZONE
    public function treasury_in(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'Numeric'],
            'nom' => ['required', 'string'],
            'raison' => ['required', 'string'],
            'description' => ['nullable', 'string'],
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

        $connected_user = Auth::user();
        $nom = $request->nom;
        $raison = $request->raison;
        $description = $request->description;

        $is_collector = $connected_user->roles->first()->name === Roles::RECOUVREUR;
        $type = $is_collector ? Transations::TREASURY_RZ_IN : Transations::TREASURY_GF_IN;

        // Nouveau versement
        $versement = new Treasury([
            'name' => $nom,
            'amount' => $montant,
            'reason' => $raison,
            'type' => $type,
            'id_manager' => $connected_user->id,
            'description' => $description,
        ]);
        $versement->save();

        //on credite le compte du donneur
        $caisse = $connected_user->caisse->first();
        $caisse->solde = $caisse->solde + $montant;
        $caisse->save();

        if($is_collector) {
            // Augmenter la dette si l'opération est éffectué par un RZ
            $connected_user->dette = $connected_user->dette + $montant;
            $connected_user->save();
        }

        // Renvoyer un message de succès
        return response()->json([
            'message' => 'Encaissement effectué avec succès',
            'status' => true,
            'data' => [
                'treasury' => $versement,
                'manager' => $versement->manager,
            ]
        ]);
    }

    /**
     * Creer un Decaissement
     */
    // GESTIONNAIRE DE FLOTTE
    // RESPONSABLE DE ZONE
    public function treasury_out(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'Numeric'],
            'nom' => ['required', 'string'],
            'raison' => ['required', 'string'],
            'description' => ['nullable', 'string'],
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

        $connected_user = Auth::user();
        $nom = $request->nom;
        $raison = $request->raison;
        $description = $request->description;

        $is_collector = $connected_user->roles->first()->name === Roles::RECOUVREUR;
        $type = $is_collector ? Transations::TREASURY_RZ_OUT : Transations::TREASURY_GF_OUT;

        $caisse = $connected_user->caisse->first();

        if($caisse->solde < $montant) {
            return response()->json([
                'message' => 'Solde caisse insuffisant pour cet opération',
                'status' => false,
                'data' => null
            ]);
        }

        // Nouveau versement
        $versement = new Treasury([
            'name' => $nom,
            'amount' => $montant,
            'reason' => $raison,
            'type' => $type,
            'id_manager' => $connected_user->id,
            'description' => $description,
        ]);
        $versement->save();

        //on credite le compte du donneur
        $caisse->solde = $caisse->solde - $montant;
        $caisse->save();

        if($is_collector) {
            // Augmenter la dette si l'opération est éffectué par un RZ
            $connected_user->dette = $connected_user->dette - $montant;
            $connected_user->save();
        }

        // Renvoyer un message de succès
        return response()->json([
            'message' => 'Décaissement effectué avec succès',
            'status' => true,
            'data' => [
                'treasury' => $versement,
                'manager' => $versement->manager,
            ]
        ]);
    }

    /**
     * Lister les Encaissements
     */
    // GESTIONNAIRE DE FLOTTE
    // RESPONSABLE DE ZONE
    public function treasuries_in()
    {
        $user = Auth::user();
        $userRole = $user->roles->first()->name;

        if($userRole === Roles::GESTION_FLOTTE) {
            $versements = Treasury::where('id_manager', $user->id)
                ->where('type', Transations::TREASURY_GF_IN)
                ->orderBy('created_at', 'desc')
                ->paginate(9);
        } else if($userRole === Roles::RECOUVREUR) {
            $versements = Treasury::where('id_manager', $user->id)
                ->where('type', Transations::TREASURY_RZ_IN)
                ->orderBy('created_at', 'desc')
                ->paginate(9);
        } else {
            $versements = Treasury::where('type', Transations::TREASURY_GF_IN)
                ->orWhere('type', Transations::TREASURY_RZ_IN)
                ->orderBy('created_at', 'desc')
                ->paginate(9);
        }

        $versements_response =  $this->treasuriesResponse($versements->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'treasuries' => $versements_response,
                'hasMoreData' => $versements->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister les Decaissements
     */
    // GESTIONNAIRE DE FLOTTE
    // RESPONSABLE DE ZONE
    public function treasuries_out()
    {
        $user = Auth::user();
        $userRole = $user->roles->first()->name;

        if($userRole === Roles::GESTION_FLOTTE) {
            $versements = Treasury::where('id_manager', $user->id)
                ->where('type', Transations::TREASURY_GF_OUT)
                ->orderBy('created_at', 'desc')
                ->paginate(9);
        } else if($userRole === Roles::RECOUVREUR) {
            $versements = Treasury::where('id_manager', $user->id)
                ->where('type', Transations::TREASURY_RZ_OUT)
                ->orderBy('created_at', 'desc')
                ->paginate(9);
        } else {
            $versements = Treasury::where('type', Transations::TREASURY_GF_OUT)
                ->orWhere('type', Transations::TREASURY_RZ_OUT)
                ->orderBy('created_at', 'desc')
                ->paginate(9);
        }

        $versements_response =  $this->treasuriesResponse($versements->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'treasuries' => $versements_response,
                'hasMoreData' => $versements->hasMorePages(),
            ]
        ]);
    }

    // Build treasuries return data
    private function treasuriesResponse($treasuries)
    {
        $returnedTreasuries = [];

        foreach($treasuries as $treasury)
        {
            $returnedTreasuries[] = [
                'treasury' => $treasury,
                'manager' => $treasury->manager
            ];
        }

        return $returnedTreasuries;
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Treasury;
use App\Enums\Roles;
use App\Enums\Transations;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Recouvrement as Notif_recouvrement;

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
     * //Creer un Encaissement
     */
    public function treasury_in(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'Numeric'],
            'nom' => ['required', 'string'],
            'raison' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'recu' => ['nullable', 'file', 'max:10000']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        //recuperer la caisse de l'utilisateur connecté
        $user = Auth::user();

        // Récupérer les données validées
        $recu = null;
        if ($request->hasFile('recu') && $request->file('recu')->isValid()) {
            $recu = $request->recu->store('files/recu/versement');
        }

        $nom = $request->nom;
        $raison = $request->raison;
        $montant = $request->montant;
        $description = $request->description;

        $userRole = $user->roles->first()->name;
        if($userRole === Roles::GESTION_FLOTTE) {
            // Gestionnaire de flotte
            // Nouveau versement
            $versement = new Treasury([
                'receipt' => $recu,
                'name' => $nom,
                'amount' => $montant,
                'reason' => $raison,
                'type' => Transations::TREASURY_GF_IN,
                'id_manager' => $user->id,
                'description' => $description,
            ]);
        } else {
            // responsable de zone
            // Nouveau versement
            $versement = new Treasury([
                'receipt' => $recu,
                'name' => $nom,
                'amount' => $montant,
                'reason' => $raison,
                'type' => Transations::TREASURY_RZ_IN,
                'id_manager' => $user->id,
                'description' => $description,
            ]);
        }

        // creation du versement
        if ($versement->save())
        {
            //notification du donneur
            $user->notify(new Notif_recouvrement([
                'data' => $versement,
                'message' => "Nouvel encaissement de votre part"
            ]));

            //on credite le compte du donneur
            $caisse = $user->caisse->first();

            if($userRole === Roles::GESTION_FLOTTE) {
                // Gestionnaire de flotte
                $caisse->solde = $caisse->solde + $montant;
            } else {
                // responsable de zone
                $caisse->solde = $caisse->solde - $montant;
            }

            $caisse->save();

            // Renvoyer un message de succès
            return response()->json([
                'message' => 'Encaissement effectué avec succès',
                'status' => true,
                'data' => [
                    'treasury' => $versement,
                    'manager' => $versement->manager,
                ]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de la Creation',
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * //Creer un Decaissement
     */
    public function treasury_out(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'Numeric'],
            'nom' => ['required', 'string'],
            'raison' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'recu' => ['nullable', 'file', 'max:10000']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        //recuperer la caisse de l'utilisateur connecté
        $user = Auth::user();

        // Récupérer les données validées
        $recu = null;
        if ($request->hasFile('recu') && $request->file('recu')->isValid()) {
            $recu = $request->recu->store('files/recu/versement');
        }

        $nom = $request->nom;
        $raison = $request->raison;
        $montant = $request->montant;
        $description = $request->description;

        $userRole = $user->roles->first()->name;
        if($userRole === Roles::GESTION_FLOTTE) {
            // Gestionnaire de flotte
            // Nouveau versement
            $versement = new Treasury([
                'receipt' => $recu,
                'name' => $nom,
                'amount' => $montant,
                'reason' => $raison,
                'type' => Transations::TREASURY_GF_OUT,
                'id_manager' => $user->id,
                'description' => $description,
            ]);
        } else {
            // responsable de zone
            // Nouveau versement
            $versement = new Treasury([
                'receipt' => $recu,
                'name' => $nom,
                'amount' => $montant,
                'reason' => $raison,
                'type' => Transations::TREASURY_RZ_OUT,
                'id_manager' => $user->id,
                'description' => $description,
            ]);
        }

        $caisse = $user->caisse->first();

        $threshold = ($userRole === Roles::GESTION_FLOTTE) ? $caisse->solde : -1 * $caisse->solde;

        if($threshold >= $montant) {
            // creation du versement
            if ($versement->save())
            {
                //notification du donneur
                $user->notify(new Notif_recouvrement([
                    'data' => $versement,
                    'message' => "Nouveau décaissement de votre part"
                ]));

                //on credite le compte du donneur
                if($userRole === Roles::GESTION_FLOTTE) {
                    // Gestionnaire de flotte
                    $caisse->solde = $caisse->solde - $montant;
                } else {
                    // responsable de zone
                    $caisse->solde = $caisse->solde + $montant;
                }

                $caisse->save();

                // Renvoyer un message de succès
                return response()->json([
                    'message' => 'Décaissement effectué avec succès',
                    'status' => true,
                    'data' => [
                        'treasury' => $versement,
                        'manager' => $versement->manager,
                    ]
                ]);
            } else {
                // Renvoyer une erreur
                return response()->json([
                    'message' => 'Erreur lors de la Creation',
                    'status' => false,
                    'data' => null
                ]);
            }
        } else {
            // Solde insuffisant
            return response()->json([
                'message' => 'Solde insufisant',
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * ////lister les Encaissements
     */
    public function treasuries_in()
    {
        $user = Auth::user();
        $userRole = $user->roles->first()->name;

        if($userRole === Roles::GESTION_FLOTTE) {
            $versements = Treasury::where('id_manager', $user->id)->where('type', Transations::TREASURY_GF_IN)->orderBy('created_at', 'desc')->paginate(6);
        } else if($userRole === Roles::RECOUVREUR) {
            $versements = Treasury::where('id_manager', $user->id)->where('type', Transations::TREASURY_RZ_IN)->orderBy('created_at', 'desc')->paginate(6);
        } else {
            $versements = Treasury::where('type', Transations::TREASURY_GF_IN)
                ->orWhere('type', Transations::TREASURY_RZ_IN)
                ->orderBy('created_at', 'desc')->paginate(6);
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
     * ////lister les Decaissements
     */
    public function treasuries_out()
    {
        $user = Auth::user();
        $userRole = $user->roles->first()->name;

        if($userRole === Roles::GESTION_FLOTTE) {
            $versements = Treasury::where('id_manager', $user->id)->where('type', Transations::TREASURY_GF_OUT)->orderBy('created_at', 'desc')->paginate(6);
        } else if($userRole === Roles::RECOUVREUR) {
            $versements = Treasury::where('id_manager', $user->id)->where('type', Transations::TREASURY_RZ_OUT)->orderBy('created_at', 'desc')->paginate(6);
        } else {
            $versements = Treasury::where('type', Transations::TREASURY_GF_OUT)
                ->orWhere('type', Transations::TREASURY_RZ_OUT)
                ->orderBy('created_at', 'desc')->paginate(6);
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

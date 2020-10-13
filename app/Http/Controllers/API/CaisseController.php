<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Caisse;
use App\Versement;
use App\Enums\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Recouvrement as Notif_recouvrement;

class CaisseController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct(){

        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$ges_flotte");
    }

    /**
     * //Creer un Encaissement
     */
    public function encassement(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_donneur' => ['required', 'Numeric'], //id de l'utilisateur qui verse l'argent
            'montant' => ['required', 'Numeric'],
            'recu' => ['required', 'file', 'max:10000']
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'message' => "Le formulaire contient des champs mal renseignés",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //On verifi si le donneur est vraiment utilisateur
        if (!($donneur = User::find($request->id_donneur))) {
            return response()->json(
                [
                    'message' => "l'utilisateur en paramettre n'existe pas en BD",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //recuperer la caisse de l'utilisateur qui effecttue le verssement
        $caisse_donneur = $donneur->caisse->first();

        //recuperer la caisse de l'utilisateur connecté
        $user = Auth::user();
        $caisse = $user->caisse->first();

        // Récupérer les données validées
        $recu = null;
        if ($request->hasFile('recu') && $request->file('recu')->isValid()) {
            $recu = $request->recu->store('files/recu/versement');
        }
        
        $id_donneur = $request->id_donneur;
        $montant = $request->montant;
        $id_caisse = $caisse->id;
        $add_by = $user->id;
        $note = "pour un versement";

        // Nouveau versement
        $versement = new Versement ([
            'recu' => $recu,
            'correspondant' => $id_donneur,
            'montant' => $montant,
            'id_caisse' => $id_caisse,
            'add_by' => $add_by,
            'note' => $note
        ]);

        // creation du versement
        if ($versement->save()) {

            //notification du donneur
            $donneur = User::find($request->id_donneur);
            $donneur->notify(new Notif_recouvrement([
                'data' => $versement,
                'message' => "Nouveau versement de votre part"
            ]));

            //on credite le compte du donneur
            $caisse_donneur->solde = $caisse_donneur->solde + $montant;
            $caisse_donneur->save();

            //on credite le compte de la gestionnaire de flotte
            $caisse->solde = $caisse->solde + $montant;
            $caisse->save();

            $versements = Versement::All();
            $encaissements = [];
            foreach ($versements as $_versement) {
                $id_caisse_gestionnaire = Caisse::where('id_user', $_versement->add_by)->first();
                if ($id_caisse_gestionnaire->id == $_versement->id_caisse) {
                    $encaissements[] = [
                        'versement' => $_versement,
                        'gestionnaire' => User::find($_versement->add_by),
                        'recouvreur' => User::find($_versement->correspondant),
                    ];
                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'versements' => $encaissements
                    ]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la Creation',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * //Creer un Decaissement
     */
    public function decaissement(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_receveur' => ['required', 'Numeric'], //id de l'utilisateur qui recoit l'argent
            'montant' => ['required', 'Numeric'],
            'recu' => ['required', 'file', 'max:10000']
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'message' => "Le formulaire contient des champs mal renseignés",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //On verifi si le receveur est vraiment utilisateur
        if (!($receveur = User::find($request->id_receveur))) {
            return response()->json(
                [
                    'message' => "l'utilisateur en paramettre n'existe pas en BD",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //recuperer la caisse de l'utilisateur qui recoit le verssement
        $caisse_receveur = $receveur->caisse->first();

        //recuperer la caisse de l'utilisateur connecté
        $user = Auth::user();
        $caisse = $user->caisse->first();

        //On verifi si le compte du payeur est suffisant
        if ($caisse->solde < $request->montant) {
            return response()->json(
                [
                    'message' => "le solde du payeur est insuffisant",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // Récupérer les données validées
        $recu = null;
        if ($request->hasFile('recu') && $request->file('recu')->isValid()) {
            $recu = $request->recu->store('files/recu/versement');
        }
        $receveur = $request->id_receveur;
        $montant = $request->montant;
        $id_caisse = $caisse_receveur->id;
        $add_by = $user->id;
        $note = "pour un Decaissement";

        // Nouveau decaissement
        $decaissement = new Versement ([
            'recu' => $recu,
            'correspondant' => $receveur,
            'montant' => $montant,
            'id_caisse' => $id_caisse,
            'add_by' => $add_by,
            'note' => $note
        ]);

        // creation du decaissement
        if ($decaissement->save()) {

            //notification du receveur
            $receveur = User::find($receveur);
            $receveur->notify(new Notif_recouvrement([
                'data' => $decaissement,
                'message' => "Nouveau decaissement de votre part"
            ]));

            //on debite le compte du receveur
            $caisse_receveur->solde = $caisse_receveur->solde - $montant;
            $caisse_receveur->save();

            //on debite le compte de la gestionnaire de flotte
            $caisse->solde = $caisse->solde - $montant;
            $caisse->save();
            $getionnaire_id = Auth::user()->id;
            $versements = Versement::All();
            $decaissements = [];
            foreach ($versements as $versement) {
                $id_caisse_gestionnaire = Caisse::where('id_user', $getionnaire_id)->first();
                if ($id_caisse_gestionnaire->id != $versement->id_caisse) {
                    $decaissements[] = [
                        'versement' => $versement,
                        'gestionnaire' => User::find($versement->add_by),
                        'receveur' => User::find($versement->correspondant),
                    ];
                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'versements' => $decaissements
                    ]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la Creation',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * ////lister les Encaissements
     */
    public function encaissement_list()
    {
        $getionnaire_id = Auth::user()->id;
        $versements = Versement::All();
        $encaissements = [];
        foreach ($versements as $versement) {
            $id_caisse_gestionnaire = Caisse::where('id_user', $getionnaire_id)->first();
            if ($id_caisse_gestionnaire->id == $versement->id_caisse) {
                $encaissements[] = [
                    'versement' => $versement,
                    'gestionnaire' => User::find($versement->add_by),
                    'recouvreur' => User::find($versement->correspondant),
                ];
            }
        }
        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'versements' => $encaissements
                ]
            ]
        );
    }

    /**
     * ////lister les Decaissements
     */
    public function decaissement_list()
    {
        $getionnaire_id = Auth::user()->id;
        $versements = Versement::All();
        $decaissements = [];
        foreach ($versements as $versement) {
            $id_caisse_gestionnaire = Caisse::where('id_user', $getionnaire_id)->first();
            if ($id_caisse_gestionnaire->id != $versement->id_caisse) {
                $decaissements[] = [
                    'versement' => $versement,
                    'gestionnaire' => User::find($versement->add_by),
                    'recouvreur' => User::find($versement->correspondant),
                ];
            }
        }
        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'versements' => $decaissements
                ]
            ]
        );
    }

    /**
     * ////Details d'un verssement
     */
    public function versement_details($id)
    {
        $versement = Versement::find($id);

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => $versement
            ]
        );
    }
}

<?php

namespace App\Http\Controllers\API;

use App\Puce;
use App\Role;
use App\User;
use App\Movement;
use App\Liquidite;
use App\Operation;
use App\Versement;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Enums\Transations;
use Illuminate\Http\Request;
use App\Notifications\Recouvrement;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use App\Notifications\Approvisionnement;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Recouvrement as Notif_recouvrement;

class CaisseController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $rz = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$ges_flotte|$rz|$superviseur");
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
            'recu' => ['nullable', 'file', 'max:10000']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi si le donneur est vraiment utilisateur
        if (!($donneur = User::find($request->id_donneur))) {
            return response()->json([
                'message' => "l'utilisateur en paramettre n'existe",
                'status' => false,
                'data' => null
            ]);
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
        if ($versement->save())
        {
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

            // Renvoyer un message de succès
            return response()->json([
                'message' => 'Encaissement effectué avec succès',
                'status' => true,
                'data' => [
                    'versement' => $versement,
                    'gestionnaire' => User::find($versement->add_by),
                    'recouvreur' => User::find($versement->correspondant),
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
     * Creer un Decaissement
     */
    // GESTIONNAIRE DE FLOTTE
    // SUPERVISEUR
    // RESPONSABLE DE ZONE
    public function decaissement(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_receveur' => ['required', 'numeric'], //id de l'utilisateur qui recoit l'argent
            'montant' => ['required', 'Numeric'],
            'raison' => ['nullable', 'string'],
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

        //On verifi si le receveur est vraiment utilisateur
        $receveur = User::find($request->id_receveur);
        if (is_null($receveur)) {
            return response()->json([
                'message' => "Receveur non reconnu",
                'status' => false,
                'data' => null
            ]);
        }

        //recuperer la caisse de l'utilisateur connecté
        $connected_user = Auth::user();
        if ($receveur->id == $connected_user->id) {
            return response()->json([
                'message' => "Vous ne pouvez pas éffectuer cette opération à vous même",
                'status' => false,
                'data' => null
            ]);
        }

        $caisse_emetteur = $connected_user->caisse->first();

        if ($caisse_emetteur->solde < $montant) {
            return response()->json([
                'message' => "Solde caisse insuffisant pour éffectuer cette opération",
                'status' => false,
                'data' => null
            ]);
        }

        // Nouveau decaissement
        $decaissement = new Versement ([
            'correspondant' => $receveur->id,
            'montant' => $montant,
            'add_by' => $connected_user->id,
            'note' => Transations::INTERNAL_TREASURY_OUT,
            'statut' => Statut::EN_COURS,
            'recu' => $request->raison
        ]);
        $decaissement->save();

        $caisse_emetteur->solde = $caisse_emetteur->solde - $montant;
        $caisse_emetteur->save();

        // Garder le mouvement de caisse éffectué par la GF
        Movement::create([
            'name' => $decaissement->related->name,
            'type' => Transations::INTERNAL_TREASURY_OUT,
            'in' => 0,
            'out' => $decaissement->montant,
            'balance' => $caisse_emetteur->solde,
            'id_user' => $connected_user->id,
        ]);

        //notification du receveur
        $message = "Décaissement éffectué par " . $connected_user->name;
        $receveur->notify(new Notif_recouvrement([
            'data' => $decaissement,
            'message' => $message
        ]));

        // Renvoyer un message de succès
        return response()->json([
            'message' => "Décaissement effectué avec succès",
            'status' => true,
            'data' => [
                'versement' => $decaissement,
                'gestionnaire' => $decaissement->user,
                'recouvreur' => $decaissement->related,
            ]
        ]);
    }

    /**
     * Creer une passation de service
     */
    // GESTIONNAIRE DE FLOTTE
    public function passation(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_receveur' => ['required', 'Numeric'], //id de l'utilisateur qui recoit l'argent
            'montant' => ['required', 'Numeric']
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

        //On verifi si le receveur est vraiment utilisateur
        $receveur = User::find($request->id_receveur);
        if (is_null($receveur)) {
            return response()->json([
                'message' => "Gestionnaire de flotte invalide",
                'status' => false,
                'data' => null
            ]);
        }

        $connected_user = Auth::user();

        //On verifi si le receveur est different de l'emetteur
        if ($receveur->id === $connected_user->id) {
            return response()->json([
                'message' => "Impossible d'éffectuer une passation de service à soi même",
                'status' => false,
                'data' => null
            ]);
        }

        $connected_user_caisse = $connected_user->caisse->first();

        // Vérifier le montant dans la caisse
        if($connected_user_caisse->solde < $montant) {
            return response()->json([
                'message' => "Solde caisse insuffisant pour éffectuer cette opération",
                'status' => false,
                'data' => null
            ]);
        }

        // Récupérer les données validées
        $add_by = $connected_user->id;

        // Nouveau decaissement
        $decaissement = new Versement ([
            'correspondant' => $receveur->id,
            'montant' => $montant,
            'add_by' => $add_by,
            'note' => Transations::INTERNAL_HANDOVER,
            'statut' => Statut::EN_COURS
        ]);
        $decaissement->save();

        // Cantonner le montant à passer
        $connected_user_caisse->solde = $connected_user_caisse->solde - $montant;
        $connected_user_caisse->save();

        // Garder le mouvement de caisse éffectué par la GF
        Movement::create([
            'name' => $decaissement->related->name,
            'type' => Transations::INTERNAL_HANDOVER,
            'in' => 0,
            'out' => $decaissement->montant,
            'balance' => $connected_user_caisse->solde,
            'id_user' => $connected_user->id,
        ]);

        //notification du receveur
        $message = "Passation de service éffectuée par " . $connected_user->name;
        $receveur->notify(new Notif_recouvrement([
            'data' => $decaissement,
            'message' => $message
        ]));

        // Renvoyer un message de succès
        return response()->json([
            'message' => 'Passation de service effectuée avec succès',
            'status' => true,
            'data' => [
                'versement' => $decaissement,
                'emetteur' => $decaissement->user,
                'recepteur' => $decaissement->related,
            ]
        ]);
    }

    /**
     * Confirmer la passation de service
     */
    // GESTIONNAIRE DE FLOTTE
    public function approuve_passation($id)
    {
        $versement = Versement::find($id);
        //si le destockage n'existe pas
        if (is_null($versement)) {
            return response()->json([
                'message' => "La passation de service n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($versement->statut === Statut::EFFECTUER) {
            return response()->json([
                'message' => "La passation de service a déjà été confirmée",
                'status' => false,
                'data' => null
            ]);
        }

        //on approuve le destockage
        $versement->statut = Statut::EFFECTUER;
        $versement->save();
        $connected_user = Auth::user();

        // Notifier la GF qui a initier la passation de service
        $message = "Passation de service confirmé par " . $connected_user->name;
        $versement->user->notify(new Notif_recouvrement([
            'data' => $versement,
            'message' => $message
        ]));

        $caisse_recepteur = $connected_user->caisse->first();
        $montant = $versement->montant;

        //on credite le compte de la gestionnaire de flotte
        $caisse_recepteur->solde = $caisse_recepteur->solde + $montant;
        $caisse_recepteur->save();

        // Garder le mouvement de caisse éffectué par la GF
        Movement::create([
            'name' => $versement->user->name,
            'type' => Transations::INTERNAL_HANDOVER,
            'in' => $versement->montant,
            'out' => 0,
            'balance' => $caisse_recepteur->solde,
            'id_user' => $connected_user->id,
        ]);

        return response()->json([
            'message' => "Passation de service confirmé avec succès",
            'status' => true,
            'data' => null
        ]);
    }

    /**
     * Lister les Encaissements
     */
    // GESTIONNAIRE DE FLOTTE
    // SUPERVISEUR
    // RESPONSABLE DE ZONE
    public function encaissement_list()
    {
        $user = Auth::user();

        $versements = Versement::where('correspondant', $user->id)
            ->where('note', Transations::INTERNAL_TREASURY_OUT)
            ->orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'versements' => $this->paymentsResponse($versements->items()),
                'hasMoreData' => $versements->hasMorePages(),
            ]
        ]);
    }

    /**
     * Confirmer encaissement
     */
    // GESTIONNAIRE DE FLOTTE
    // SUPERVISOR
    // RESPONSABLE DE ZONE
    public function approuve_encaissement($id)
    {
        $versement = Versement::find($id);
        //si le destockage n'existe pas
        if (is_null($versement)) {
            return response()->json([
                'message' => "L'encaissement interne n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($versement->statut === Statut::EFFECTUER) {
            return response()->json([
                'message' => "L'encaissement interne a déjà été confirmé",
                'status' => false,
                'data' => null
            ]);
        }

        //on approuve le destockage
        $versement->statut = Statut::EFFECTUER;
        $versement->save();
        $connected_user = Auth::user();

        // Donnees emetteur
        $emetteur = $versement->user;
        $emetteur_role = $emetteur->roles->first()->name;

         // Notifier l'emetteur;
        $message = "Encaissement confirmé par " . $connected_user->name;
        $emetteur->notify(new Notif_recouvrement([
            'data' => $versement,
            'message' => $message
        ]));

        $recepteur_role = $connected_user->roles->first()->name;
        $caisse_recepteur = $connected_user->caisse->first();
        $montant = $versement->montant;

        //on credite le compte de la gestionnaire de flotte
        $caisse_recepteur->solde = $caisse_recepteur->solde + $montant;
        $caisse_recepteur->save();

        // Garder le mouvement de caisse éffectué par la GF
        Movement::create([
            'name' => $versement->user->name,
            'type' => Transations::INTERNAL_TREASURY_IN,
            'in' => $versement->montant,
            'out' => 0,
            'balance' => $caisse_recepteur->solde,
            'id_user' => $connected_user->id,
        ]);

        // Reduire la dette si emetteur RZ
        if($emetteur_role === Roles::RECOUVREUR) {
            $emetteur->dette = $emetteur->dette - $montant;
            $emetteur->save();
        }

        // Augmenter la dette si recepteur RZ
        if($recepteur_role === Roles::RECOUVREUR) {
            $connected_user->dette = $connected_user->dette + $montant;
            $connected_user->save();
        }

        return response()->json([
            'message' => "Encaissement confirmé avec succès",
            'status' => true,
            'data' => null
        ]);
    }

    /**
     * ////lister les Encaissements
     */
    public function encaissement_list_all()
    {
        $user = Auth::user();
        $connected_user_role = $user->roles->first()->name;

        if ($connected_user_role === Roles::RECOUVREUR) {
            $versements = Versement::where('correspondant', $user->id)->where('note', "pour un versement")->orderBy('created_at', 'desc')->get();
        } elseif($connected_user_role === Roles::GESTION_FLOTTE) {
            $versements = Versement::where('add_by', $user->id)->where('note', "pour un versement")->orderBy('created_at', 'desc')->get();
        } else {
            $versements = Versement::where('note', "pour un versement")->orderBy('created_at', 'desc')->get();
        }

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'versements' => $this->paymentsResponse($versements),
            ]
        ]);
    }

    /**
     * Lister les Decaissements
     */
    // GESTIONNAIRE DE FLOTTE
    // SUPERVISEUR
    // RESPONSABLE DE ZONE
    public function decaissement_list()
    {
        $user = Auth::user();

        $versements = Versement::where('add_by', $user->id)
            ->where('note', Transations::INTERNAL_TREASURY_OUT)
            ->orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'versements' => $this->paymentsResponse($versements->items()),
                'hasMoreData' => $versements->hasMorePages(),
            ]
        ]);
    }

    /**
     * ////lister touts les Decaissements
     */
    public function decaissement_list_all()
    {
        $user = Auth::user();
        $connected_user_role = $user->roles->first()->name;

        if ($connected_user_role === Roles::RECOUVREUR) {
            $versements = Versement::where('correspondant', $user->id)->where('note', "pour un Decaissement")->orderBy('created_at', 'desc')->get();
        } elseif($connected_user_role === Roles::GESTION_FLOTTE) {
            $versements = Versement::where('add_by', $user->id)->where('note', "pour un Decaissement")->orderBy('created_at', 'desc')->get();
        } else {
            $versements = Versement::where('note', "pour un Decaissement")->orderBy('created_at', 'desc')->get();
        }

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'versements' => $this->paymentsResponse($versements),
            ]
        ]);
    }

    /**
     * Lister les passations de service
     */
    // GESTIONNAIRE DE FLOTTE
    // SUPERVISEUR
    public function passations_list()
    {
        $connected_user = Auth::user();
        $user_role = $connected_user->roles->first()->name;
        $id_manager = $user_role === Roles::GESTION_FLOTTE;

       if($id_manager) {
            $getionnaire_id = $connected_user->id;
            $versements = Versement::where('note', Transations::INTERNAL_HANDOVER)
                ->where(function($query) use ($getionnaire_id) {
                    $query->where('add_by', $getionnaire_id);
                    $query->orWhere('correspondant', $getionnaire_id);
                })
                ->orderBy('statut', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate(9);
        } else {
            $versements = Versement::where('note', Transations::INTERNAL_HANDOVER)
                ->orderBy('statut', 'desc')
                ->orderBy('created_at', 'desc')
                ->paginate(9);
        }

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'versements' => $this->handoverResponse($versements->items()),
                'hasMoreData' => $versements->hasMorePages(),
            ]
        ]);
    }

    /**
     * ////lister touts les passations
     */
    public function passations_list_all()
    {
        $getionnaire_id = Auth::user()->id;

        $versements = Versement::where('note', 'pour une passation de service')
            ->where(function($query) use ($getionnaire_id) {
                $query->where('add_by', $getionnaire_id);
                $query->orWhere('correspondant', $getionnaire_id);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'versements' => $this->handoverResponse($versements),
            ]
        ]);
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

    /**
     * //Creer une depence.
     */
    public function depence(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'description' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'Numeric'],
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
        // Récupérer les données validées
        $description = $request->description;
        $montant = $request->montant;

        // Nouvelle depence
        $operation = new Operation ([
            'id_motif' => null,
            'id_user' => Auth::user()->id,
            'flux' => Statut::DEPENSE,
            'montant' => $montant,
            'description' => $description
        ]);

        // creation de La depence
        if ($operation->save()) {

            //Database Notification
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Recouvrement([
                        'data' => $operation,
                        'message' => "Nouvelle depence faite par un client"
                    ]));
                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Oppération créée',
                    'status' => true,
                    'data' => ['operation' => $operation]
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
     * ////Details d'une depence
     */
    public function depence_details($id)
    {
        $depence = Operation::where('flux', Statut::DEPENSE)->where('id', $id)->first();
        $user = !is_null($depence) ? User::find($depence->id_user) : null;

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['depence' => $depence, 'user' => $user]
            ]
        );
    }

    /**
     * ////lister les depences par utilisateur
     */
    public function depence_user($id_utilisateur)
    {

        $depences = Operation::where('flux', Statut::DEPENSE)->where('id_user', $id_utilisateur)->get();
        $all_depence = [];
        foreach ($depences as $depence) {
            $all_depence[] = [
                'depences' => $depence,
                'user' => User::find($depence->id_user),
                'user' => !is_null($depence) ? User::find($depence->id_user) : null,
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'all_depence' => $all_depence
                ]
            ]
        );

    }

    /**
     * ////lister toutes les depences
     */
    public function depence_list()
    {
        $depences = Operation::where('flux', Statut::DEPENSE)->get();
        $all_depence = [];
        foreach ($depences as $depence) {
            $all_depence[] = [
                'depences' => $depence,
                'user' => User::find($depence->id_user),
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'all_depence' => $all_depence
                ]
            ]
        );
    }

    /**
     * //Creer une acquisition.
     */
    public function acquisition(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'description' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'Numeric'],
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
        // Récupérer les données validées
        $description = $request->description;
        $montant = $request->montant;

        // Nouvelle acquisition
        $operation = new Operation ([
            'id_motif' => null,
            'id_user' => Auth::user()->id,
            'flux' => Statut::ACQUISITION,
            'montant' => $montant,
            'description' => $description
        ]);

        // creation de La acquisition
        if ($operation->save()) {

            //On implique la dette
            $caisse = Auth::user()->caisse->first();
            $caisse->solde = $caisse->solde - $montant;
            $caisse->save();

            //Database Notification
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Recouvrement([
                        'data' => $operation,
                        'message' => "Nouvelle depence faite par un client"
                    ]));
                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Oppération créée',
                    'status' => true,
                    'data' => ['operation' => $operation]
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
     * ////Details d'une acquisition
     */
    public function acquisition_details($id)
    {
        $acquisition = Operation::where('flux', Statut::ACQUISITION)->where('id', $id)->first();

        $user = !is_null($acquisition) ? User::find($acquisition->id_user) : null;

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['acquisitions' => $acquisition, 'user' => $user]
            ]
        );
    }

    /**
     * ////lister les acquisitions par acquisitions
     */
    public function acquisition_user($id_utilisateur)
    {

        $acquisitions = Operation::where('flux', Statut::ACQUISITION)->where('id_user', $id_utilisateur)->get();
        $all_acquisitions = [];
        foreach ($acquisitions as $acquisition) {
            $all_acquisitions[] = [
                'acquisition' => $acquisition,
                'user' => !is_null($acquisition) ? User::find($acquisition->id_user) : null,
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'all_acquisitions' => $all_acquisitions
                ]
            ]
        );
    }

    /**
     * ////lister toutes les acquisitions
     */
    public function acquisition_list()
    {
        $acquisitions = Operation::where('flux', Statut::ACQUISITION)->get();
        $all_acquisitions = [];
        foreach ($acquisitions as $acquisition) {
            $all_acquisitions[] = [
                'acquisition' => $acquisition,
                'user' => !is_null($acquisition) ? User::find($acquisition->id_user) : null,
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'acquisitions' => $all_acquisitions
                ]
            ]
        );
    }

    // Build handovers return data
    private function handoverResponse($handovers)
    {
        $returnedHandovers = [];

        foreach($handovers as $versement)
        {
            $returnedHandovers[] = [
                'versement' => $versement,
                'emetteur' => $versement->user,
                'recepteur' => $versement->related,
            ];
        }

        return $returnedHandovers;
    }

    /**
     * //Creer un echange de liquidité
     */
    public function give_to_rz(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_reception' => ['required', 'Numeric'], //id de l'utilisateur qui va recevoir de l'argent
            'montant' => ['required', 'Numeric']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi si le receveur est vraiment utilisateur
        if (!($reception = User::find($request->id_reception))) {
            return response()->json([
                'message' => "l'utilisateur en paramettre n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //recuperer la caisse de l'utilisateur qui recoit le verssement
        $caisse_reception = $reception->caisse->first();

        //recuperer la caisse de l'utilisateur connecté
        $user = Auth::user();
        $caisse = $user->caisse->first();
        $balance = $caisse->solde;

        // Chercher les puces du RZ
        $sims = Puce::where('id_rz', $user->id)->get();
        $sims_amount = $sims->sum('solde');

        // Déduire le solde caisse potentiel
        if($balance <= 0) {
            $positiveBalance = -1 * $balance;
            if($balance >= $positiveBalance) $cash_balance = 0;
            else $cash_balance = $positiveBalance - $sims_amount;
        } else $cash_balance = 0;

        //On verifi si le solde du payeur est suffisant
        if ($cash_balance < $request->montant) {
            return response()->json([
                'message' => "Solde caisse insuffisant pour cet opération",
                'status' => false,
                'data' => null
            ]);
        }

        // Récupérer les données validées
        $id_reception = $request->id_reception;
        $id_emission = $user->id;
        $montant = $request->montant;
        $note = "pour un versement";

        // Nouveau transfert
        $transfert = new Liquidite ([
            'id_user' => $id_emission,
            'id_reception' => $id_reception,
            'montant' => $montant,
            'statut' => Statut::EN_ATTENTE,
            'note' => $note,
        ]);

        // creation du versement
        if ($transfert->save())
        {
            //notification du donneur
            $reception = User::find($id_reception);
            $reception->notify(new Approvisionnement([
                'data' => $transfert,
                'message' => "Nouvel échange de liquidité"
            ]));

            //on credite le compte du donneur
            $caisse->solde = $caisse->solde + $montant;
            $caisse->save();

            //on débite le solde de celui qui recoi l'argent
            $caisse_reception->solde = $caisse_reception->solde - $montant;
            $caisse_reception->save();

            // Renvoyer un message de succès
            return response()->json([
                'message' => 'Transfert de liquidité effectué avec succès',
                'status' => true,
                'data' => [
                    'liqudite' => $transfert,
                    'emetteur' => $user,
                    'recepteur' => $reception,
                ]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de l\'operation',
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * ////lister mes échanges de liquidité
     */
    public function give_to_rz_list()
    {
        $my_id = Auth::user()->id;

        $liqudites = Liquidite::where('id_user', $my_id)->orwhere('id_reception', $my_id)->orderBy('created_at', 'desc')->paginate(6);

        $liquidates_response =  $this->liquidatesResponse($liqudites->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'liqudites' => $liquidates_response,
                'hasMoreData' => $liqudites->hasMorePages(),
            ]
        ]);
    }

    /**
     * ////Details d'un verssement
     */
    public function give_to_rz_details($id)
    {
        $liqudite = Liquidite::find($id);

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => $liqudite
            ]
        );
    }

    /**
     * ////Details d'un verssement
     */
    public function give_to_rz_approuve($id)
    {
        $liqudite = Liquidite::find($id);
        $liqudite->statut = Statut::APPROUVE;
        $liqudite->save();

        return response()->json([
            'message' => 'Confirmation de la liquidité éffectuée avec succès',
            'status' => true,
            'data' => null
        ]);
    }

    // Build liquidates return data
    private function liquidatesResponse($liquidates)
    {
        $returnedLiquidates = [];

        foreach($liquidates as $liquidate)
        {
            $returnedLiquidates[] = [
                'liqudite' => $liquidate,
                'emetteur' => $liquidate->emission,
                'recepteur' => $liquidate->reception,
            ];
        }

        return $returnedLiquidates;
    }

    // Build payments return data
    private function paymentsResponse($payments)
    {
        $returnedPayments = [];

        foreach($payments as $versement)
        {
            $returnedPayments[] = [
                'versement' => $versement,
                'gestionnaire' => $versement->user,
                'recouvreur' => $versement->related,
            ];
        }

        return $returnedPayments;
    }
}

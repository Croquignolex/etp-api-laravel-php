<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Puce;
use App\Role;
use App\Type_puce;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Flottage_interne;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Flottage as Notif_flottage;

class Flottage_interneController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$superviseur|$ges_flotte|$recouvreur");
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    Public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_puce_from' => ['required', 'numeric'],
            'id_puce_to' => ['required', 'numeric'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // On verifi que la puce passée en paramettre existe
        if (Puce::find($request->id_puce_from) && Puce::find($request->id_puce_to)) {

            //On recupère la puce qui envoie
            $puce_from = Puce::find($request->id_puce_from);

            //On recupère la puce qui recoit
            $puce_to = Puce::find($request->id_puce_to);

            //on recupère les types de la puce qui envoie
            $type_puce_from = Type_puce::find($puce_from->type)->name;

            //on recupère les types de la puce qui recoit
            $type_puce_to = Type_puce::find($puce_to->type)->name;

            //On se rassure que les puces passée en paramettre respectent toutes les conditions
            if ($type_puce_from != Statut::FLOTTAGE_SECONDAIRE || $type_puce_to != Statut::FLOTTAGE) {
                return response()->json(
                    [
                        'message' => "Choisier des puces valide pour la transation",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

        }else {
            return response()->json(
                [
                    'message' => "une ou plusieurs puces entrées n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //On se rassure que le solde est suffisant
        if ($puce_from->solde <= $request->montant) {
            return response()->json(
                [
                    'message' => "le solde est insuffisant",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //on debite le solde du supperviseur
        $puce_from->solde = $puce_from->solde - $request->montant;

        //On credite le solde de la GF
        $puce_to->solde = $puce_to->solde + $request->montant;

        //Le supperviseur
        $supperviseur = Auth::user();

        // Nouveau flottage
        $flottage_interne = new Flottage_interne([
            'id_user' => $supperviseur->id,
            'id_sim_from' => $puce_from->id,
            'id_sim_to' => $puce_to->id,
            'reference' => null,
            'statut' => Statut::EFFECTUER,
            'note' => null,
            'type' => Roles::GESTION_FLOTTE,
            'montant' => $request->montant,
            'reste' => null
        ]);

        //si l'enregistrement du flottage a lieu
        if ($flottage_interne->save()) {

            $puce_from->save();
            $puce_to->save();

            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_flottage([
                        'data' => $flottage_interne,
                        'message' => "Nouveau flottage Interne"
                    ]));
                }
            }

            //On recupere les Flottages
            $flottage_internes = Flottage_interne::get();

            $flottages = [];

            foreach($flottage_internes as $flottage_interne) {

                //recuperer la puce du superviseur
                $puce_emetrice = Puce::find($flottage_interne->id_sim_from);

                //if ($puce_emetrice->type_puce->name == Statut::FLOTTAGE_SECONDAIRE) {

                    //recuperer la puce du gestionnaire de flotte
                    $puce_receptrice = Puce::find($flottage_interne->id_sim_to);

                    //recuperer celui qui a effectué le flottage
                    $superviseur = User::find($flottage_interne->id_user);


                    $flottages[] = [
                        'puce_receptrice' => $puce_receptrice,
                        'puce_emetrice' => $puce_emetrice,
                        'superviseur' => $superviseur,
                        'flottage' => $flottage_interne
                    ];
                //}
            }

                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => "Le flottage c'est bien passé",
                        'status' => true,
                        'data' => ['flottages' => $flottages]
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
    Public function flottage_interne_rz(Request $request) {

        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_puce_from' => ['required', 'numeric'],
            'id_puce_to' => ['required', 'numeric'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // On verifi que les puces passées en paramettre existent
        if (Puce::find($request->id_puce_from) && Puce::find($request->id_puce_to)) {

            //On recupère la puce qui envoie
            $puce_from = Puce::find($request->id_puce_from);

            //On recupère la puce qui recoit
            $puce_to = Puce::find($request->id_puce_to);

            //on recupère les types de la puce qui envoie
            $type_puce_from = Type_puce::find($puce_from->type)->name;

            //on recupère les types de la puce qui recoit
            $type_puce_to = Type_puce::find($puce_to->type)->name;

            //on recupère la flotte des puces
            $flote_to = $puce_to->flote;
            $flote_from = $puce_from->flote;

            //On se rassure que les puces passée en paramettre respectent toutes les conditions
            if ($type_puce_from != Statut::FLOTTAGE_SECONDAIRE && $type_puce_to != Statut::PUCE_RZ) {
                return response()->json([
                    'message' => "Choisir une puce valide pour la reception",
                    'status' => false,
                    'data' => null
                ]);
            }

        } else {
            return response()->json([
                'message' => "Une ou plusieurs puces entrées n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

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

        //on debite le solde du supperviseur
        $puce_from->solde = $puce_from->solde - $request->montant;

        //On credite le solde du responsable de zonne
        $puce_to->solde = $puce_to->solde + $request->montant;

        // On débite la caisse de l'utilisateur associé à la puce receptrice si celui-ci est un responsable de zone
        $receiver = $puce_to->rz;
        if($receiver !== null) {
            $caisse_rz2 = $receiver->caisse->first();
            $caisse_rz2->solde = $caisse_rz2->solde - $request->montant;
            $caisse_rz2->save();
        }

        //Le supperviseur
        $supperviseur = Auth::user();

        // Nouveau flottage
        $flottage_interne = new Flottage_interne([
            'id_user' => $supperviseur->id,
            'id_sim_from' => $puce_from->id,
            'id_sim_to' => $puce_to->id,
            'reference' => null,
            'statut' => Statut::EFFECTUER,
            'note' => null,
            'type' => Roles::SUPERVISEUR,
            'montant' => $request->montant,
            'reste' => 0
        ]);

        //si l'enregistrement du flottage a lieu
        if ($flottage_interne->save()) {

            $puce_from->save();
            $puce_to->save();

            if($receiver !== null) {
                //Notification du responsable de zone
                $puce_to->rz->notify(new Notif_flottage([
                    'data' => $flottage_interne,
                    'message' => "Nouveau flottage Interne"
                ]));
            }

            //recuperer la puce du superviseur
            $puce_emetrice = Puce::find($flottage_interne->id_sim_from);

            //recuperer la puce du gestionnaire de flotte
            $puce_receptrice = Puce::find($flottage_interne->id_sim_to);

            //recuperer celui qui a effectué le flottage
            $superviseur = User::find($flottage_interne->id_user);

            // Renvoyer un message de succès
            return response()->json([
                'message' => "Transfert de flotte effectué avec succès",
                'status' => true,
                'data' => [
                    'puce_receptrice' => $puce_receptrice,
                    'puce_emetrice' => $puce_emetrice,
                    'utilisateur' => $superviseur,
                    'flottage' => $flottage_interne
                ]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors du flottage',
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * ////lister tous les flottages interne
     */
    public function list_all()
    {
        $connected_user_role = Auth::user()->roles->first()->name;

        if ($connected_user_role === Roles::RECOUVREUR) {
            $transfers = Flottage_interne::orderBy('created_at', 'desc')->get();
            $transfers_response =  $this->transfersResponse($transfers);
            $hasMoreData = false;
        } else {
            $transfers = Flottage_interne::orderBy('created_at', 'desc')->paginate(6);
            $transfers_response =  $this->transfersResponse($transfers->items());
            $hasMoreData = $transfers->hasMorePages();
        }

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'flottages' => $transfers_response,
                'hasMoreData' => $hasMoreData,
            ]
        ]);
    }

    /**
     * ////lister les flottages faits par un superviseur vers un responsable de zonne precis
     */
    public function list_all_flottage_interne_by_rz($id)
    {
        //On recupere les Flottages
        $flottage_internes = Flottage_interne::where('type', Roles::RECOUVREUR)->get();

        $flottages = [];

        foreach($flottage_internes as $flottage_interne)
        {
            //recuperer la puce du gestionnaire de flotte
            $puce_receptrice = Puce::find($flottage_interne->id_sim_to);

            //On recupère seulement les flotage du responsable passé en paramettre
            if ($puce_receptrice->id_rz == $id){
                //recuperer la puce du superviseur
                $puce_emetrice = Puce::find($flottage_interne->id_sim_from);

                //recuperer celui qui a effectué le flottage
                $superviseur = User::find($flottage_interne->id_user);

                $flottages[] = [
                    'puce_receptrice' => $puce_receptrice,
                    'puce_emetrice' => $puce_emetrice,
                    'superviseur' => $superviseur,
                    'flottage' => $flottage_interne
                ];
            }
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $flottages]
            ]
        );
    }

    /**
     * ////details d'un flottages interne
     */
    public function show($id_flottage)
    {

        if (!Flottage_interne::Find($id_flottage)){

            return response()->json(
                [
                    'message' => "le flottage specifié n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }
        //On recupere le Flottage
        $flottage = Flottage_interne::find($id_flottage);

        //recuperer celui qui a effectué le flottage
        $superviseur = User::find($flottage->id_user);

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottage' => $flottage,'superviseur' => $superviseur ]
            ]
        );

    }

    /**
     * @param Request $request
     * @return JsonResponse
     * Creer un Flottage d'un responsable de zone vers un gestionnaire de flotte ou vers un superviseur
     */
    Public function flottage_interne_rz_gf(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_puce_from' => ['required', 'numeric'],
            'id_puce_to' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // On verifi que la puce passée en paramettre existe
        if (Puce::find($request->id_puce_from) && Puce::find($request->id_puce_to)) {

            //On recupère la puce qui envoie
            $puce_from = Puce::find($request->id_puce_from);

            //On recupère la puce qui recoit
            $puce_to = Puce::find($request->id_puce_to);

            //on recupère les types de la puce qui envoie
            $type_puce_from = Type_puce::find($puce_from->type)->name;

            //on recupère les types de la puce qui recoit
            $type_puce_to = Type_puce::find($puce_to->type)->name;

            //On se rassure que la puce qui envoie est RZ
            if ($type_puce_from != Statut::PUCE_RZ) {
                return response()->json([
                    'message' => "Choisir une puce valide pour la transation",
                    'status' => false,
                    'data' => null
                ]);
            }

            //On se rassure que la puce qui recoit est GF ou SUP ou RZ
            if ($type_puce_to != Statut::FLOTTAGE && $type_puce_to != Statut::FLOTTAGE_SECONDAIRE && $type_puce_to != Statut::PUCE_RZ) {
                return response()->json([
                    'message' => "Choisir une puce valide pour la reception",
                    'status' => false,
                    'data' => null
                ]);
            }
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
                'message' => "Le solde est insuffisant",
                'status' => false,
                'data' => null
            ]);
        }

        //on debite le solde du RZ
        $puce_from->solde = $puce_from->solde - $request->montant;

        //On credite le solde de la puce qui recoit
        $puce_to->solde = $puce_to->solde + $request->montant;

        //On credite la caisse du responsable de zonne, il rembourse sa dette
        $caisse_rz = $puce_from->rz->caisse->first();
        $caisse_rz->solde = $caisse_rz->solde + $request->montant;

        // On débite la caisse de l'utilisateur associé à la puce receptrice si celui-ci est un responsable de zone
        $receiver = $puce_to->rz;
        if($receiver !== null) {
            $caisse_rz2 = $receiver->caisse->first();
            $caisse_rz2->solde = $caisse_rz2->solde - $request->montant;
            $caisse_rz2->save();
        }

        //Le responsable de zonne
        $rz = Auth::user();

        // Nouveau flottage
        $flottage_interne = new Flottage_interne([
            'id_user' => $rz->id,
            'id_sim_from' => $puce_from->id,
            'id_sim_to' => $puce_to->id,
            'reference' => null,
            'statut' => Statut::EFFECTUER,
            'note' => null,
            'type' => Roles::RETOUR_RZ,
            'montant' => $request->montant,
            'reste' => null
        ]);

        //si l'enregistrement du flottage a lieu
        if ($flottage_interne->save()) {

            $puce_from->save();
            $puce_to->save();
            $caisse_rz->save();

            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $role2 = Role::where('name', Roles::SUPERVISEUR)->first();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name]) || $user->hasRole([$role2->name])) {

                    $user->notify(new Notif_flottage([
                        'data' => $flottage_interne,
                        'message' => "Nouveau flottage Interne"
                    ]));
                }
            }

            //recuperer la puce du superviseur
            $puce_emetrice = Puce::find($flottage_interne->id_sim_from);

            //recuperer la puce du gestionnaire de flotte
            $puce_receptrice = Puce::find($flottage_interne->id_sim_to);

            //recuperer celui qui a effectué le flottage
            $superviseur = User::find($flottage_interne->id_user);

            // Renvoyer un message de succès
            return response()->json([
                'message' => "Transfert de flotte effectué avec succès",
                'status' => true,
                'data' => [
                    'puce_receptrice' => $puce_receptrice,
                    'puce_emetrice' => $puce_emetrice,
                    'utilisateur' => $superviseur,
                    'flottage' => $flottage_interne
                ]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors du flottage',
                'status' => false,
                'data' => null
            ]);
        }

    }

    /**
     * @param Request $request
     * @return JsonResponse
     * Creer un Flottage d'un Agent ETP vers un gestionnaire de flotte
     */
    Public function flottage_interne_ae_gf(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_puce_from' => ['required', 'numeric'],
            'id_puce_to' => ['required', 'numeric'],
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

        // On verifi que les puces passées en paramettre existent
        if (Puce::find($request->id_puce_from) && Puce::find($request->id_puce_to)) {

            //On recupère la puce qui envoie
            $puce_from = Puce::find($request->id_puce_from);

            //On recupère la puce qui recoit
            $puce_to = Puce::find($request->id_puce_to);

            //on recupère les types de la puce qui envoie
            $type_puce_from = Type_puce::find($puce_from->type)->name;

            //on recupère les types de la puce qui recoit
            $type_puce_to = Type_puce::find($puce_to->type)->name;

            //On se rassure que la puce qui envoie est bien AGENT ETP
            if ($type_puce_from != Statut::RESOURCE) {
                return response()->json(
                    [
                        'message' => "Choisisser une puce valide pour la transation",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

            //On se rassure que la puce qui recoit est GF
            if ($type_puce_to != Statut::FLOTTAGE) {
                return response()->json(
                    [
                        'message' => "Choisier une puce valide pour la reception",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

        }else {
            return response()->json(
                [
                    'message' => "une ou plusieurs puces entrées n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //On se rassure que le solde est suffisant
        if ($puce_from->solde < $request->montant) {
            return response()->json(
                [
                    'message' => "le montant est insuffisant",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //on debite le solde de l'agent ETP
        $puce_from->solde = $puce_from->solde - $request->montant;

        //On credite le solde de la puce qui recoit
        $puce_to->solde = $puce_to->solde + $request->montant;

        //On credite la caisse de l'agent ETP, il rembourse sa dette
        $caisse_ae = $puce_from->agent->user->caisse->first();
        $caisse_ae->solde = $caisse_ae->solde + $request->montant;

        //La gestionnaire de flotte
        $gf = Auth::user();

        $ae = $puce_from->agent->user;


        // Nouveau flottage
        $flottage_interne = new Flottage_interne([
            'id_user' => $gf->id,
            'id_sim_from' => $puce_from->id,
            'id_sim_to' => $puce_to->id,
            'reference' => null,
            'statut' => Statut::EFFECTUER,
            'note' => null,
            'type' => Roles::RETOUR_AE,
            'montant' => $request->montant,
            'reste' => null
        ]);

        //si l'enregistrement du flottage a lieu
        if ($flottage_interne->save()) {

            $puce_from->save();
            $puce_to->save();
            $caisse_ae->save();

            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $role2 = Role::where('name', Roles::SUPERVISEUR)->first();

            //Database Notification
            $ae->notify(new Notif_flottage([
                'data' => $flottage_interne,
                'message' => "Nouveau rembourssement par flotte"
            ]));


            //On recupere les Flottages
            $flottage_internes = Flottage_interne::get();

            $flottages = [];

            foreach($flottage_internes as $flottage_interne) {

                //recuperer la puce de l'agent ETP
                $puce_emetrice = Puce::find($flottage_interne->id_sim_from);

                //recuperer la puce qui recoit
                $puce_receptrice = Puce::find($flottage_interne->id_sim_to);


                //recuperer celui qui a effectué le flottage
                $gf = User::find($flottage_interne->id_user);

                if(
                    ($puce_emetrice->agent->user->id !== null && $puce_emetrice->agent->user->id === Auth::user()->id) ||
                    ($puce_receptrice->agent->user->id !== null && $puce_receptrice->agent->user->id === Auth::user()->id)
                ) {
                    // Take only the current collector receiving sims
                    $flottages[] = [
                        'puce_receptrice' => $puce_receptrice,
                        'puce_emetrice' => $puce_emetrice,
                        'gestionnaire' => $gf,
                        'flottage' => $flottage_interne
                    ];
                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => "Le flottage c'est bien passé",
                    'status' => true,
                    'data' => ['flottages' => $flottages]
                ]
            );
        } else {

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

    // Build transfers return data
    private function transfersResponse($tranfers)
    {
        $returnedTransfers = [];

        $connected_user_role = Auth::user()->roles->first()->name;

        foreach($tranfers as $flottage_interne)
        {
            //recuperer la puce du superviseur
            $puce_emetrice = Puce::find($flottage_interne->id_sim_from);

            //recuperer la puce du gestionnaire de flotte
            $puce_receptrice = Puce::find($flottage_interne->id_sim_to);

            //recuperer celui qui a effectué le flottage
            $superviseur = User::find($flottage_interne->id_user);

            if ($connected_user_role === Roles::RECOUVREUR) {
                if(
                    ($puce_emetrice->rz !== null && $puce_emetrice->rz->id === Auth::user()->id) ||
                    ($puce_receptrice->rz !== null && $puce_receptrice->rz->id === Auth::user()->id)
                ) {
                    // Take only the current collector receiving sims
                    $returnedTransfers[] = [
                        'puce_receptrice' => $puce_receptrice,
                        'puce_emetrice' => $puce_emetrice,
                        'utilisateur' => $superviseur,
                        'flottage' => $flottage_interne
                    ];
                }
            } else {
                // Take all if it is not a collector
                $returnedTransfers[] = [
                    'puce_receptrice' => $puce_receptrice,
                    'puce_emetrice' => $puce_emetrice,
                    'utilisateur' => $superviseur,
                    'flottage' => $flottage_interne
                ];
            }
        }

        return $returnedTransfers;
    }
}

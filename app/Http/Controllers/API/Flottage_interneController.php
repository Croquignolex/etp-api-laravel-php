<?php

namespace App\Http\Controllers\API;

use App\Puce;
use App\User;
use App\Role;
use App\Type_puce;
use App\Enums\Roles;
use App\Transaction;
use App\Enums\Statut;
use App\Flottage_interne;
use App\Enums\Transations;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Flottage as Notif_flottage;
use App\Notifications\Destockage as Notif_destockage;

class Flottage_interneController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $comptable = Roles::COMPATBLE;
        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $controlleur = Roles::CONTROLLEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$superviseur|$ges_flotte|$recouvreur|$controlleur|$comptable");
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
                        'flottage' => $flottage_interne,
                        'operateur' => $puce_emetrice->flote,
                        'type_recepteur' => $puce_receptrice->type_puce
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
     * Approuver le transfert de flotte
     */
    // GESTIONNAIRE DE FLOTTE
    // SUPERVISEUR
    // RESPONSABLE DE ZONE
    public function approuve($id)
    {
        //si le destockage n'existe pas
        $transfert_flotte = Flottage_interne::find($id);
        if (is_null($transfert_flotte)) {
            return response()->json([
                'message' => "Le transfert de flotte n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($transfert_flotte->statut === Statut::EFFECTUER) {
            return response()->json([
                'message' => "Le transfert de flotte a déjà été confirmé",
                'status' => false,
                'data' => null
            ]);
        }

        $montant = $transfert_flotte->montant;
        $puce_emetrice = $transfert_flotte->puce_emetrice;
        $puce_receptrice = $transfert_flotte->puce_receptrice;

        //on approuve le destockage
        $transfert_flotte->statut = Statut::EFFECTUER;

        // Traitement des flottes
        $puce_receptrice->solde = $puce_receptrice->solde + $montant;
        $puce_receptrice->save();

        $type_puce_receptrice = $puce_receptrice->type_puce->name;
        $type_puce_emetrice = $puce_emetrice->type_puce->name;

        $connected_user = Auth::user();

        // Augmenter la dette si nous somme en presence d'un RZ en puce receptrice
        if ($type_puce_receptrice === Statut::PUCE_RZ) {
            $rz = $puce_receptrice->rz;
            if(!is_null($rz)) {
                $rz->dette = $rz->dette + $montant;
                $rz->save();
            }
        }

        // Garder la transaction éffectué par la GF
        Transaction::create([
            'type' => Transations::FLEET_TRANSFER,
            'in' => $transfert_flotte->montant,
            'out' => 0,
            'id_operator' => $puce_receptrice->flote->id,
            'id_left' => $puce_receptrice->id,
            'id_right' => $puce_emetrice->id,
            'balance' => $puce_receptrice->solde,
            'id_user' => $connected_user->id,
        ]);

        // Dimunuer la dette si nous somme en presence d'un RZ en puce emetrice
        if ($type_puce_emetrice === Statut::PUCE_RZ) {
            $rz = $puce_emetrice->rz;
            if(!is_null($rz)) {
                $rz->dette = $rz->dette - $montant;
                $rz->save();
            }
        }

        $transfert_flotte->save();
        $message = "Transfert de flotte Apprové par " . $connected_user->name;

        if($type_puce_emetrice === Statut::PUCE_RZ)
        {
            // Notifier le RZ emetteur
            $rz = $puce_emetrice->rz;
            $rz->notify(new Notif_destockage([
                'data' => $transfert_flotte,
                'message' => $message
            ]));
        }
        else if($type_puce_emetrice === Statut::FLOTTAGE)
        {
            $users = User::all();
            // Notifier tous les superviseur
            foreach ($users as $user) {
                if ($user->hasRole([ Roles::GESTION_FLOTTE])) {
                    $user->notify(new Notif_destockage([
                        'data' => $transfert_flotte,
                        'message' => $message
                    ]));
                }
            }
        }
        else if($type_puce_emetrice === Statut::FLOTTAGE_SECONDAIRE)
        {
            $users = User::all();
            // Notifier tous les gestionnaires de flottes
            foreach ($users as $user) {
                if ($user->hasRole([Roles::SUPERVISEUR])) {
                    $user->notify(new Notif_destockage([
                        'data' => $transfert_flotte,
                        'message' => $message
                    ]));
                }
            }
        }

        return response()->json([
            'message' => "Transfert de flotte apprové avec succès",
            'status' => true,
            'data' => null
        ]);
    }

    /**
     * Lister tous les flottages interne
     */
    // GESTIONNAIRE DE FLOTTE
    // SUPERVISEUR
    // RESPONSABLE DE ZONE
    public function list_all()
    {
        $connected_user_role = Auth::user()->roles->first()->name;

        if ($connected_user_role === Roles::RECOUVREUR) {
            $transfers = Flottage_interne::where('type', 'like', Statut::PUCE_RZ . '%')
                ->orWhere('type', 'like', '%' . Statut::PUCE_RZ)
                ->orderBy('created_at', 'desc')
                ->paginate(9);
        } else if($connected_user_role === Roles::GESTION_FLOTTE) {
            $transfers = Flottage_interne::where('type', 'like', Statut::FLOTTAGE . '%')
                ->orWhere('type', 'like', '%' . Statut::FLOTTAGE)
                ->orderBy('created_at', 'desc')
                ->paginate(9);
        } else {
            $transfers = Flottage_interne::orderBy('created_at', 'desc')->paginate(9);
        }

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'flottages' => $this->transfersResponse($transfers->items()),
                'hasMoreData' => $transfers->hasMorePages(),
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
    public function flottage_interne_rz_gf(Request $request)
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
    public function flottage_interne_ae_gf(Request $request)
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

        foreach($tranfers as $flottage_interne)
        {
            $puce_emetrice = $flottage_interne->puce_emetrice;
            $puce_receptrice = $flottage_interne->puce_receptrice;
            $superviseur = $flottage_interne->user;

            // Take all if it is not a collector
            $returnedTransfers[] = [
                'puce_receptrice' => $puce_receptrice,
                'puce_emetrice' => $puce_emetrice,
                'utilisateur' => $superviseur,
                'flottage' => $flottage_interne,
                'operateur' => $puce_emetrice->flote,
                'type_recepteur' => $puce_receptrice->type_puce,
                'rz' => $puce_receptrice->rz,
            ];
        }

        return $returnedTransfers;
    }
}

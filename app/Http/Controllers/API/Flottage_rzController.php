<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Puce;
use App\Role;
use App\Type_puce;
use App\Flottage_Rz;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Flottage_interne;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Flottage as Notif_flottage;

class Flottage_rzController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $rz = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$superviseur|$ges_flotte|$rz");
    }

    //creer le flottage
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
                ]
            );
        }

        // On verifi que la puce passée en paramettre existe
        if (!is_null(Puce::find($request->id_puce_from)) && !is_null(Puce::find($request->id_puce_to))) {

            //On recupère la puce qui envoie
            $puce_from = Puce::find($request->id_puce_from);

            //On recupère la puce qui recoit
            $puce_to = Puce::find($request->id_puce_to);

            //on recupère les types de la puce qui envoie
            $type_puce_from = Type_puce::find($puce_from->type)->name;

            //on recupère les types de la puce qui recoit
            $type_puce_to = Type_puce::find($puce_to->type)->name;

            //On se rassure que les puces passée en paramettre respectent toutes les conditions
            if ($type_puce_to != Statut::PUCE_RZ) {
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
                'message' => "Le solde de la puce émetrice insuffisant",
                'status' => false,
                'data' => null
            ]);
        }

        //on debite le solde du gestionnaire de flotte
        $puce_from->solde = $puce_from->solde - $request->montant;

        //On credite le solde du rz
        $puce_to->solde = $puce_to->solde + $request->montant;

        //On debite la caisse du rz
        $rz_caisse = $puce_to->rz->caisse->first();
        $rz_caisse->solde = $rz_caisse->solde - $request->montant;

        $gestionnaire = Auth::user();

        // Nouveau flottage
        $flottage_rz = new Flottage_interne([
            'id_user' => $gestionnaire->id,
            'id_sim_from' => $puce_from->id,
            'id_sim_to' => $puce_to->id,
            'reference' => null,
            'statut' => Statut::EFFECTUER,
            'type' => 'GF->RZ',
            'note' => null,
            'montant' => $request->montant,
            'reste' => null
        ]);

        //si l'enregistrement du flottage a lieu
        if ($flottage_rz->save()) {

            $puce_from->save();
            $puce_to->save();
            $rz_caisse->save();

            $role = Role::where('name', Roles::RECOUVREUR)->first();

            //Database Notification
            //notifier le responsable de zone
            $puce_to->rz->notify(new Notif_flottage([
                'data' => $flottage_rz,
                'message' => "Nouveau flottage Dans votre puce"
            ]));

            //recuperer la puce du superviseur
            $puce_emetrice = Puce::find($flottage_rz->id_sim_from);

            //recuperer la puce du gestionnaire de flotte
            $puce_receptrice = Puce::find($flottage_rz->id_sim_to);

            //recuperer celui qui a effectué le flottage
            $superviseur = User::find($flottage_rz->id_user);

            // Renvoyer un message de succès
            return response()->json([
                'message' => "Transfert de flotte effectué avec succès",
                'status' => true,
                'data' => [
                    'puce_receptrice' => $puce_receptrice,
                    'puce_emetrice' => $puce_emetrice,
                    'utilisateur' => $superviseur,
                    'flottage' => $flottage_rz
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
     * ////creer un flottages par un responsable de zone
     */
    Public function flottage_by_rz(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_sim_rz' => ['required', 'numeric'],
            'id_agent' => ['required', 'numeric'],
            'id_sim_agent' => ['required', 'numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // On verifi que l'agent' en paramettre existe
        if (is_null(User::find($request->id_agent))) {
            return response()->json([
                'message' => "Cet agent n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $user = User::find($request->id_agent);
        $agent = $user->agent->first();

        // On verifi que la puce passée en paramettre existe
        if (!is_null(Puce::find($request->id_sim_agent))) {

            //On recupère la puce qui recoit
            $puce_to = Puce::find($request->id_sim_agent);

            //on recupère les types de la puce qui recoit
            $type_puce_to = Type_puce::find($puce_to->type)->name;

            //on recupère la flotte de la puce qui recoit
            $flote_to = $puce_to->flote;

            //On se rassure que la puce agent appartient à l'agent passé en paramettre

            if (!($puce_to->id_agent == $agent->id)) {
                return response()->json([
                    'message' => "La puce n'appartient pas à l'agent",
                    'status' => false,
                    'data' => null
                ]);
            }
        } else {
            return response()->json([
                'message' => "La puce agent entrées n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //On se rassure que le la puce du responsable de zonne existe
        if (!is_null(Puce::find($request->id_sim_rz))) {

            //On recupère la puce qui envoit
            $puce_from = Puce::find($request->id_sim_rz);

            $flote_from = $puce_from->flote;

            //On se rassure que la puce appartient au responsable de zonne
            if (!($puce_from->id_rz == Auth::user()->id)) {
                return response()->json([
                    'message' => "La puce n'appartient pas au responsable de zone",
                    'status' => false,
                    'data' => null
                ]);
            }
        } else {
            return response()->json([
                'message' => "La puce du responsable de zone entrées n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifie que c'est les puce du meme reseau
        if ($flote_to != $flote_from) {
            return response()->json([
                'message' => "Vous devez choisir les puces du même opérateur",
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

        //on debite le solde du responsable de zonne
        $puce_from->solde = $puce_from->solde - $request->montant;

        //on credite la flotte de l'agent s'il est de ETP
        if ($type_puce_to != Statut::RESOURCE){
            $puce_to->solde = $puce_to->solde + $request->montant;
        }

        $responsable = Auth::user();

        // Nouveau flottage
        $flottage_rz = new Flottage_Rz([
            'id_responsable_zone' => $responsable->id,
            'id_agent' => $agent->id,
            'id_sim_agent' => $puce_to->id,
            'reference' => null,
            'statut' => Statut::EFFECTUER,
            'montant' => $request->montant,
            'reste' => 0
        ]);

        //si l'enregistrement du flottage a lieu
        if ($flottage_rz->save()) {

            $puce_from->save();
            $puce_to->save();

            //Notification
            $agent->user->notify(new Notif_flottage([
                'data' => $flottage_rz,
                'message' => "Nouveau flottage Dans votre puce"
            ]));

            $puce_agent = Puce::find($flottage_rz->id_sim_agent);

            // Renvoyer un message de succès
            return response()->json([
                'message' => "Flottage éffectué avec succès",
                'status' => true,
                'data' => [
                    'approvisionnement' => $flottage_rz,
                    'user' => $user,
                    'agent' => $agent,
                    'puce_receptrice' => $puce_agent,
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
     * ////lister tous les flottages rz
     */
    public function list_all()
    {
        //On recupere les Flottages
        $flottage_internes = Flottage_interne::get();

        $flottages = [];

        foreach($flottage_internes as $flottage_interne) {

            //recuperer la puce d'envoie
            $puce_emetrice = Puce::find($flottage_interne->id_sim_from);
            if ($puce_emetrice->type_puce->name == Statut::FLOTTAGE) {

                //recuperer la puce de reception
                $puce_receptrice = Puce::find($flottage_interne->id_sim_to);

                //recuperer celui qui a effectué le flottage
                $rz = User::find($flottage_interne->id_user);


                $flottages[] = [
                    'puce_receptrice' => $puce_receptrice,
                    'puce_emetrice' => $puce_emetrice,
                    'rz' => $rz,
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
        $rz = User::find($flottage->id_user);

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottage' => $flottage,'rz' => $rz ]
            ]
        );

    }

    /**
     * ////lister les flottages effectués par un responsable de zone precis
     */
    public function list_flottage_rz_by_rz()
    {
        $user = Auth::user();
        $userRole = $user->roles->first()->name;

        if($userRole === Roles::RECOUVREUR) {
            $flottages_rz = Flottage_Rz::where('id_responsable_zone', $user->id)->orderBy('created_at', 'desc')->paginate(6);
            $demandes_flotes =  $this->fleetsResponse($flottages_rz->items());

            return response()->json([
                'message' => "",
                'status' => true,
                'data' => [
                    'flottages' => $demandes_flotes,
                    'hasMoreData' => $flottages_rz->hasMorePages(),
                ]
            ]);
        } else {
            return response()->json([
                'message' => "Cet utilisateur n'est pas un responsable de zone",
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * ////lister les flottages effectués pour un agent precis
     */
    public function list_flottage_rz_by_agent($id)
    {

        //On recupere les Flottages rz
        $flottages_rz = Flottage_Rz::where('id_agent', $id)->get();

        $flottages = [];

        foreach($flottages_rz as $flottage_rz) {

            //puce de l'agent
            $puce_agent = Puce::find($flottage_rz->id_agent);

            $flottages[] = [
                'puce_agent' => $puce_agent,
                'flottage' => $flottage_rz
            ];

        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $flottages ]
            ]
        );
    }

    /**
     * ////lister les flottages effectués par les responsables de zone (superviseur)
     */
    public function list_all_flottage_by_rz()
    {

        //On recupere les Flottages rz
        $flottages_rz = Flottage_Rz::All();

        $flottages = [];

        foreach($flottages_rz as $flottage_rz) {

            $recouvreur = $flottage_rz->responsable_zone;

            //recuperer l'agent concerné
            $agent = $flottage_rz->agent;

            //recuperer l'utilisateur concerné
            $user = $agent->user;

            //puce de l'agenr
            $puce_agent = Puce::find($flottage_rz->id_sim_agent);

            $flottages[] = [
                'user' => $user,
                'agent' => $agent,
                'gestionnaire' => $recouvreur,
                'puce_receptrice' => $puce_agent,
                'approvisionnement' => $flottage_rz,
            ];

        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $flottages ]
            ]
        );
    }

    /**
     * ////détails d'un flottage effectué par un responsable de zone
     */
    public function show_flottage_rz($id)
    {

        //On recupere la Flottages rz
        $flottage_rz = Flottage_Rz::find($id);
        $puce_agent = null;

        if (!is_null($flottage_rz)){
            //puce de l'agent
            $puce_agent = Puce::find($flottage_rz->id_agent);
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottage' => $flottage_rz, 'puce_agent' => $puce_agent  ]
            ]
        );
    }

    // Build fleets return data
    private function fleetsResponse($fleets)
    {
        $approvisionnements = [];

        foreach($fleets as $flottage)
        {
            //recuperer l'agent concerné
            $agent = $flottage->agent;

            $user = $agent->user;

            //puce de l'agenr
            $puce_agent = Puce::find($flottage->id_sim_agent);


            $approvisionnements[] = [
                'approvisionnement' => $flottage,
                'user' => $user,
                'agent' => $agent,
                'puce_receptrice' => $puce_agent,
            ];
        }

        return $approvisionnements;
    }
}

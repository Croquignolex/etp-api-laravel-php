<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Puce;
use App\Role;
use App\Agent;
use App\Enums\Roles;
use App\Recouvrement;
use App\Enums\Statut;
use App\Approvisionnement;
use Illuminate\Http\Request;
use App\Events\NotificationsEvent;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use App\Notifications\Recouvrement as Notif_recouvrement;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Recouvrement as RecouvrementResource;

class RecouvrementController extends Controller
{
    /**

     * les conditions de lecture des methodes

     */

    function __construct(){

        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $agent = Roles::AGENT;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$agent");

    }

    Public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'Numeric'],
            'id_flottage' => ['required', 'Numeric'],
            'recu' => ['required', 'file', 'max:10000']
        ]);

        if ($validator->fails()) {
            return response()->json(
                [
                    'message' => ['error'=>$validator->errors()],
                    'status' => false,
                    'data' => null
                ]
            );
        }


        //On verifi si le flottage passée existe réellement
        if (!Approvisionnement::find($request->id_flottage)) {
            return response()->json(
                [
                    'message' => "le flottage n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //On verifi que le montant n'est pas supperieur au montant demandé
        if (Approvisionnement::find($request->id_flottage)->reste < $request->montant) {
            return response()->json(
                [
                    'message' => "Vous essayez de recouvrir plus d'argent que prevu",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //enregistrer le recu
        $recu = null;
        if ($request->hasFile('recu') && $request->file('recu')->isValid()) {
            $recu = $request->recu->store('recu');
        }

        //On recupère le flottage à traiter
        $flottage = Approvisionnement::find($request->id_flottage);

        //Montant du depot
        $montant = $request->montant;

        //L'agent concerné
        $user = User::find($flottage->demande_flote->id_user);
        //$agent = Agent::Where('id_user', $user->id)->first();

        //la puce de L'agent concerné
        $puce_agent = Puce::find($flottage->demande_flote->id_puce);

        //Caisse de l'agent concerné
        $caisse = $user->caisse->first();

        //recouvreur
        $recouvreur = Auth::user();

        // Nouveau recouvrement
        $recouvrement = new Recouvrement([
            'id_user' => $recouvreur->id,
            'id_transaction' => null,
            'id_versement' => null,
            'type_transaction' => Statut::RECOUVREMENT,
            'reference' => null,
            'montant' => $montant,
            'reste' => $montant,
            'recu' => $recu,
            'id_flottage' => $request->id_flottage,
            'statut' => Statut::EFFECTUER,
            'user_destination' => $recouvreur->id,
            'user_source' => $user->id
        ]);

        //si l'enregistrement du recouvrement a lieu
        if ($recouvrement->save()) {

            //Notification du gestionnaire de flotte
            $role = Role::where('name', Roles::RECOUVREUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Nouveau recouvrement']);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_recouvrement([
                        'data' => $recouvrement,
                        'message' => "Nouveau recouvrement"
                    ]));
                }
            }

            ////ce que le recouvrement implique

                //On credite la caisse de l'Agent pour le remboursement de la flotte recu, ce qui implique qu'il rembource ses detes à ETP
                $caisse->solde = $caisse->solde + $montant;
                $caisse->save();

                //On recupère la puce de l'agent concerné et on debite
                $puce_agent->solde = $puce_agent->solde - $montant;
                $puce_agent->save();

                //On calcule le reste à recouvrir
                $flottage->reste = $flottage->reste - $montant;

                //On change le statut du flottage
                if ($flottage->reste == 0) {
                    $flottage->statut = \App\Enums\Statut::EFFECTUER ;
                }else {
                    $flottage->statut = \App\Enums\Statut::EN_COURS ;
                }

                //Enregistrer les oppérations
                $flottage->save();

                //On recupere les recouvrement
                $recouvrements = Recouvrement::where('user_destination', $recouvreur->id)->get();

                $recouvrementsArray = [];

                foreach($recouvrements as $recouvrement) {

                    //recuperer le flottage correspondant
                    $flottage = Approvisionnement::find($recouvrement->id_flottage);

                    //recuperer celui qui a éffectué le recouvrement
                    $user = User::find($recouvrement->id_user);

                    //recuperer l'agent concerné
                    $user = User::find($recouvrement->user_source);
                    $agent = Agent::Where('id_user', $user->id)->first();

                    $recouvreur = User::find($recouvrement->user_destination);

                    //recuperer la puce de l'agent
                    $puce_agent = Puce::find($flottage->demande_flote->id_puce);

                    $recouvrementsArray[] = [
                        'recouvrement' => $recouvrement,
                        'flottage' => $flottage,
                        'user' => $user,
                        'agent' => $agent,
                        'recouvreur' => $recouvreur,
    //                'puce_agent' => $puce_agent
                    ];
                }

                // Extraction des approvisionnements

                $flottages = Approvisionnement::get();

                $approvisionnements = [];

                foreach($flottages as $flottage) {

                    //recuperer la demande correspondante
                    $demande = $flottage->demande_flote;

                    //recuperer l'agent concerné
                    $user = $demande->user;

                    //recuperer l'agent concerné
                    $agent = Agent::where('id_user', $user->id)->first();

                    // recuperer celui qui a éffectué le flottage
                    $gestionnaire = User::find($flottage->id_user);

                    //recuperer la puce de l'agent
                    $puce_receptrice = Puce::find($demande->id_puce);

                    //recuperer la puce de ETP
                    $puce_emetrice = Puce::find($flottage->from);

                    $approvisionnements[] = [
                        'approvisionnement' => $flottage,
                        'demande' => $demande,
                        'user' => $user,
                        'agent' => $agent,
                        'gestionnaire' => $gestionnaire,
                        'puce_emetrice' => $puce_emetrice,
                        'puce_receptrice' => $puce_receptrice,
                    ];
                }

                return response()->json(
                    [
                        'message' => '',
                        'status' => true,
                        'data' => [
                            'recouvrements' => $recouvrementsArray,
                            'flottages' => $approvisionnements
                        ]
                    ]
                );
        }else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors du recouvrement',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * ////details d'un recouvrement
     */
    public function show($id)
    {

            //si le recouvrement n'existe pas
            if (!($recouvrement = Recouvrement::find($id))) {
                return response()->json(
                    [
                        'message' => "le recouvrement n'existe pas",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

            return new RecouvrementResource($recouvrement);


    }

    /**
     * ////lister tous les recouvrement
     */
    public function list_all()
    {
        //On recupere les recouvrement
        $recouvrements = Recouvrement::get();

        $approvisionnements = [];

        foreach($recouvrements as $recouvrement) {

            //recuperer le flottage correspondant
            $flottage = Approvisionnement::find($recouvrement->id_flottage);

            //recuperer celui qui a éffectué le recouvrement
                $user = User::find($recouvrement->id_user);

            //recuperer l'agent concerné
                $user = User::find($recouvrement->user_source);
                $agent = Agent::Where('id_user', $user->id)->first();

                $recouvreur = User::find($recouvrement->user_destination);

            //recuperer la puce de l'agent
                $puce_agent = Puce::find($flottage->demande_flote->id_puce);

            $approvisionnements[] = [
                'recouvrement' => $recouvrement,
                'flottage' => $flottage,
                'user' => $user,
                'agent' => $agent,
                'recouvreur' => $recouvreur,
//                'puce_agent' => $puce_agent
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['recouvrements' => $approvisionnements]
            ]
        );
    }

    /**
     * ////lister les recouvrements d'un flottage
     */
    public function list_recouvrement($id)
    {
        if (!Approvisionnement::Find($id)){

            return response()->json(
                [
                    'message' => "le flottage n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }

        //On recupere les recouvrements
        $recouvrements = Recouvrement::where('id_flottage', $id)->get();


        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['recouvrements' => $recouvrements]
            ]
        );

    }

    /**
     * ////lister les recouvrements d'un RZ
     */
    public function list_recouvrement_by_rz($id)
    {
        if (!User::find($id)){

            return response()->json(
                [
                    'message' => "le Responsable de zonne n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }

        //On recupere les recouvrements
        $recouvrements = Recouvrement::where('user_destination', $id)->get();

        $approvisionnements = [];

        foreach($recouvrements as $recouvrement) {

            //recuperer le flottage correspondant
            $flottage = Approvisionnement::find($recouvrement->id_flottage);

            //recuperer celui qui a éffectué le recouvrement
            $user = User::find($recouvrement->id_user);

            //recuperer l'agent concerné
            $user = User::find($recouvrement->user_source);
            $agent = Agent::Where('id_user', $user->id)->first();

            $recouvreur = User::find($recouvrement->user_destination);

            //recuperer la puce de l'agent
            $puce_agent = Puce::find($flottage->demande_flote->id_puce);

            $approvisionnements[] = [
                'recouvrement' => $recouvrement,
                'flottage' => $flottage,
                'user' => $user,
                'agent' => $agent,
                'recouvreur' => $recouvreur,
//                'puce_agent' => $puce_agent
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['recouvrements' => $approvisionnements]
            ]
        );
    }

    /**
     * ////lister les recouvrements d'un Agent precis
     */
    public function list_recouvrement_by_agent($id)
    {
        if (!User::find($id)){

            return response()->json(
                [
                    'message' => "l'agent' n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }

        //On recupere les recouvrements
        $recouvrements = Recouvrement::where('user_source', $id)->get();

        $approvisionnements = [];

        foreach($recouvrements as $recouvrement) {

            //recuperer le flottage correspondant
            $flottage = Approvisionnement::find($recouvrement->id_flottage);

            //recuperer celui qui a éffectué le recouvrement
            $user = User::find($recouvrement->id_user);

            //recuperer l'agent concerné
            $user = User::find($recouvrement->user_source);
            $agent = Agent::Where('id_user', $user->id)->first();

            $recouvreur = User::find($recouvrement->user_destination);

            //recuperer la puce de l'agent
            $puce_agent = Puce::find($flottage->demande_flote->id_puce);

            $approvisionnements[] = [
                'recouvrement' => $recouvrement,
                'flottage' => $flottage,
                'user' => $user,
                'agent' => $agent,
                'recouvreur' => $recouvreur,
//                'puce_agent' => $puce_agent
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['recouvrements' => $approvisionnements]
            ]
        );
    }

    /**
     * ////approuver un recouvrement en espèces
     */
    public function approuve($id)
    {
        //si le recouvrement n'existe pas
        if (!($recouvrement = Recouvrement::find($id))) {
            return response()->json(
                [
                    'message' => "le recouvrement n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //on approuve le destockage
        $recouvrement->statut = Statut::EFFECTUER;

        //message de reussite
        if ($recouvrement->save()) {

            //Notification du gestionnaire de flotte
            $role = Role::where('name', Roles::RECOUVREUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Un recouvrement Approuvée']);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_recouvrement([
                        'data' => $recouvrement,
                        'message' => "Un recouvrement Approuvée"
                    ]));
                }
            }

            //On recupere les recouvrement
            $recouvrements = Recouvrement::get();

            $approvisionnements = [];

            foreach($recouvrements as $recouvrement) {

                //recuperer le flottage correspondant
                $flottage = Approvisionnement::find($recouvrement->id_flottage);

                //recuperer celui qui a éffectué le recouvrement
                $user = User::find($recouvrement->id_user);

                //recuperer l'agent concerné
                $user = User::find($recouvrement->user_source);
                $agent = Agent::Where('id_user', $user->id)->first();

                $recouvreur = User::find($recouvrement->user_destination);

                //recuperer la puce de l'agent
                $puce_agent = Puce::find($flottage->demande_flote->id_puce);

                $approvisionnements[] = [
                    'recouvrement' => $recouvrement,
                    'flottage' => $flottage,
                    'user' => $user,
                    'agent' => $agent,
                    'recouvreur' => $recouvreur,
//                'puce_agent' => $puce_agent
                ];
            }

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['recouvrements' => $approvisionnements]
                ]
            );
        }else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la confirmation',
                    'status'=>false,
                    'data' => null
                ]
            );
        }
    }
}

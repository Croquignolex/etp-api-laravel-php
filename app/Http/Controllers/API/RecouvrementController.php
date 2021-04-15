<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Puce;
use App\Role;
use App\Agent;
use App\Caisse;
use App\Enums\Roles;
use App\Recouvrement;
use App\Enums\Statut;
use App\Approvisionnement;
use Illuminate\Http\Request;
use App\Events\NotificationsEvent;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Recouvrement as Notif_recouvrement;
use App\Http\Resources\Recouvrement as RecouvrementResource;

class RecouvrementController extends Controller
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
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$agent");
    }

    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'Numeric'],
            'id_flottage' => ['required', 'Numeric'],
            'recu' => ['nullable', 'file', 'max:10000']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi si le flottage passée existe réellement
        if (!Approvisionnement::find($request->id_flottage)) {
            return response()->json([
                'message' => "Le flottage n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi que le montant n'est pas supperieur au montant demandé
        if (Approvisionnement::find($request->id_flottage)->reste < $request->montant) {
            return response()->json([
                'message' => "Vous essayez de recouvrir plus d'argent que prevu",
                'status' => false,
                'data' => null
            ]);
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
            $role = Role::where('name', Roles::RECOUVREUR)
            ->orWhere('name', Roles::GESTION_FLOTTE)->first();
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
            } else {
                $flottage->statut = \App\Enums\Statut::EN_COURS ;
            }

            //Enregistrer les oppérations
            $flottage->save();

            //gestion de la caisse de l'agent qui recouvre
            $connected_user = Auth::user();

            //la caisse de l'utilisateur connecté
            $connected_caisse = Caisse::where('id_user', $connected_user->id)->first();

            //mise à jour de la caisse de l'utilisateur qui effectue l'oppération
            if ($connected_user->hasRole([Roles::GESTION_FLOTTE])) {
                $connected_caisse->solde = $connected_caisse->solde + $montant;
            }else {
                $connected_caisse->solde = $connected_caisse->solde - $montant;
            }
            $connected_caisse->save();

            return response()->json([
                'message' => "Recouvrement d'espèces effectué avec succès",
                'status' => true,
                'data' => null
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors du recouvrement',
                'status' => false,
                'data' => null
            ]);
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
     * ////lister les recouvrement
     */
    public function list_all()
    {
        $recoveries = Recouvrement::orderBy('created_at', 'desc')->paginate(9);

        $recoveries_response =  $this->recoveriesResponse($recoveries->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvrements' => $recoveries_response,
                'hasMoreData' => $recoveries->hasMorePages(),
            ]
        ]);
    }

    /**
     * //lister tous les recouvrement
     */
    public function list_all_all()
    {
        $recoveries = Recouvrement::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvrements' => $this->recoveriesResponse($recoveries)
            ]
        ]);
    }

    /**
     * ////lister les recouvrements d'un flottage
     */
    public function list_recouvrement($id)
    {
        if (!Approvisionnement::find($id)){

            return response()->json([
                    'message' => "Le flottage n'existe pas",
                    'status' => true,
                    'data' => null
                ]
            );
        }

        //On recupere les recouvrements
        $recoveries = Recouvrement::where('id_flottage', $id)->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvrements' => $this->recoveriesResponse($recoveries)
            ]
        ]);
    }

    /**
     * ////lister les recouvrements d'un RZ
     */
    public function list_recouvrement_by_rz()
    {
        $user = Auth::user();

        if ($user->roles->first()->name !== Roles::RECOUVREUR){
            return response()->json([
                'message' => "Le responsable de zonne n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $recoveries = Recouvrement::where('id_user', $user->id)->orderBy('created_at', 'desc')->paginate(9);

        $recoveries_response =  $this->recoveriesResponse($recoveries->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvrements' => $recoveries_response,
                'hasMoreData' => $recoveries->hasMorePages(),
            ]
        ]);
    }

    /**
     * ////lister les recouvrements d'un Agent precis
     */
    public function list_recouvrement_by_agent()
    {
        $user = Auth::user();

        if ($user->roles->first()->name !== Roles::AGENT && $user->roles->first()->name !== Roles::RESSOURCE){
            return response()->json([
                'message' => "Cet utilisateur n'est pas un agent/ressource",
                'status' => false,
                'data' => null
            ]);
        }

        $recoveries = Recouvrement::where('user_source', $user->id)->orderBy('created_at', 'desc')->paginate(12);

        $recoveries_response =  $this->recoveriesResponse($recoveries->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvrements' => $recoveries_response,
                'hasMoreData' => $recoveries->hasMorePages(),
            ]
        ]);
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

                //recuperer celui qui a effectué le recouvrement
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

    // Build recoveries return data
    private function recoveriesResponse($recoveries)
    {
        $returnedRecoveries = [];

        foreach($recoveries as $recovery)
        {
            //recuperer l'agent concerné
            $user = User::find($recovery->user_source);
            $agent = $user->agent->first();

            $recouvreur = User::find($recovery->id_user);

            $returnedRecoveries[] = [
                'user' => $user,
                'agent' => $agent,
                'recouvreur' => $recouvreur,
                'recouvrement' => $recovery,
            ];
        }

        return $returnedRecoveries;
    }
}

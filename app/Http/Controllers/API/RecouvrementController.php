<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Puce;
use App\Role;
use App\Agent;
use App\Movement;
use App\Enums\Roles;
use App\Recouvrement;
use App\Enums\Statut;
use App\Enums\Transations;
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
        $comptable = Roles::COMPATBLE;
        $recouvreur = Roles::RECOUVREUR;
        $controlleur = Roles::CONTROLLEUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$agent|$controlleur|$comptable");
    }

    /**
     * Recouvrement d'espèces
     */
    // GESTIONNAIRE DE FLOTTE
    // RESPONSABLE DE ZONE
    // SUPERVISEUR
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_flottage' => ['required', 'numeric']
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

        //On verifi si le flottage passée existe réellement
        $flottage = Approvisionnement::find($request->id_flottage);
        if (is_null($flottage)) {
            return response()->json([
                'message' => "Le flottage n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi que le montant n'est pas supperieur au montant demandé
        if ($flottage->reste < $montant) {
            return response()->json([
                'message' => "Vous essayez de recouvrir plus d'espèces que prevu",
                'status' => false,
                'data' => null
            ]);
        }

        $user = $flottage->demande_flote->user;

        //Caisse de l'agent concerné
        $caisse = $user->caisse->first();
        $connected_user = Auth::user();

        // Nouveau recouvrement
        $recouvrement = new Recouvrement([
            'id_user' => $connected_user->id,
            'type_transaction' => Statut::RECOUVREMENT,
            'montant' => $montant,
            'reste' => $montant,
            'id_flottage' => $flottage->id,
            'statut' => Statut::EFFECTUER,
            'user_destination' => $connected_user->id,
            'user_source' => $user->id
        ]);
        $recouvrement->save();

        $message = "Recouvrement d'espèces éffectué par " . $connected_user->name;
        //Database Notification
        $users = User::all();
        foreach ($users as $_user) {
            if ($_user->hasRole([Roles::SUPERVISEUR])) {
                $_user->notify(new Notif_recouvrement([
                    'data' => $recouvrement,
                    'message' => $message
                ]));
            }
        }

        //notification de l'agent
        $user->notify(new Notif_recouvrement([
            'data' => $recouvrement,
            'message' => $message
        ]));

        ////ce que le recouvrement implique

        //On credite la caisse de l'Agent pour le remboursement de la flotte recu, ce qui implique qu'il rembource ses detes à ETP
        $caisse->solde = $caisse->solde - $montant;
        $caisse->save();

        //la caisse de l'utilisateur connecté
        $connected_caisse = $connected_user->caisse->first();
        // Augmenter la caisse
        $connected_caisse->solde = $connected_caisse->solde + $montant;
        $connected_caisse->save();

        $is_manager_fleeter = $flottage->user->roles->first()->name === Roles::GESTION_FLOTTE;
        $daily_report_status = $is_manager_fleeter;

        // Garder le mouvement de caisse éffectué par la GF
        Movement::create([
            'name' => $recouvrement->source_user->name,
            'type' => Transations::RECOUVREMENT,
            'in' => $recouvrement->montant,
            'out' => 0,
            'manager' => $daily_report_status,
            'balance' => $connected_caisse->solde,
            'id_user' => $connected_user->id,
        ]);

        if($connected_user->hasRole([Roles::RECOUVREUR]))
        {
            // Augmenter la caisse du RZ et augmenter sa dette
            $connected_user->dette = $connected_user->dette + $montant;
            $connected_user->save();
        }

        //On calcule le reste à recouvrir
        $flottage->reste = $flottage->reste - $montant;

        //On change le statut du flottage
        if ($flottage->reste == 0) $flottage->statut = Statut::EFFECTUER ;
        else $flottage->statut = Statut::EN_COURS ;

        //Enregistrer les oppérations
        $flottage->save();

        return response()->json([
            'message' => "Recouvrement d'espèces effectué avec succès",
            'status' => true,
            'data' => null
        ]);
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
     * Lister les recouvrement
     */
    // GESTIONNAIRE DE FLOTTE
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
     * Lister les recouvrements d'un flottage
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_recouvrement($id)
    {
        //On recupere les recouvrements
        $recoveries = Recouvrement::where('id_flottage', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvrements' => $this->recoveriesResponse($recoveries)
            ]
        ]);
    }

    /**
     * Lister les recouvrements d'un RZ
     */
    // RESPONSABLE DE ZONE
    public function list_recouvrement_by_rz()
    {
        $user = Auth::user();

        $recoveries = Recouvrement::where('id_user', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvrements' =>  $this->recoveriesResponse($recoveries->items()),
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
            foreach ($users as $_user) {

                if ($_user->hasRole([$role->name])) {

                    $_user->notify(new Notif_recouvrement([
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
            $user = $recovery->source_user;
            $agent = $user->agent->first();
            $recouvreur = $recovery->user;

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

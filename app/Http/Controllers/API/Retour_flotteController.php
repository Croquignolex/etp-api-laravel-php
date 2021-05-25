<?php

namespace App\Http\Controllers\API;

use App\Puce;
use App\User;
use App\Role;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Retour_flote;
use App\Approvisionnement;
use Illuminate\Http\Request;
use App\Events\NotificationsEvent;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Retour_flotte as Notif_retour_flotte;
use App\Http\Resources\Retour_flote as Retour_floteResource;

class Retour_flotteController extends Controller
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

    /**
     * Retour de flotte
     */
    // GESTIONNAIRE DE FLOTTE
    public function retour(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'puce_agent' => ['required', 'Numeric'],
            'puce_flottage' => ['required', 'Numeric'],
            'id_flottage' => ['required', 'Numeric'],
            'montant' => ['required', 'Numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $flottage = Approvisionnement::find($request->id_flottage);
        //On verifi si le flottage passée existe réellement
        if (is_null($flottage)) {
            return response()->json([
                'message' => "Le flottage n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $montant = $request->montant;

        // Vérification du montat à recouvrir
        if ($flottage->reste < $montant) {
            return response()->json([
                'message' => "Vous essayez de retourner plus de flotte que prevu",
                'status' => false,
                'data' => null
            ]);
        }

        $user = $flottage->demande_flote->user;
        $agent = $user->agent->first();
        $puce_agent = Puce::find($request->puce_agent);
        $puce_flottage = Puce::find($request->puce_flottage);

        //On verifi que le montant n'est pas supperieur au montant demandé
        if ($puce_agent == null || $puce_flottage == null) {
            return response()->json([
                'message' => "L'une des puce n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        if ($agent->id != $puce_agent->id_agent) {
            return response()->json([
                'message' => "Vous devez renvoyer la flotte avec une puce appartenant à l'agent qui a été flotté",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi si les puce passée appartien à au meme oppérateur de flotte
        if ($puce_flottage->flote->id != $puce_agent->flote->id) {
            return response()->json([
                'message' => "Les deux puces ne sont pas du même opérateur",
                'status' => false,
                'data' => null
            ]);
        }

        $connected_user = Auth::user();
        $type_puce = $puce_flottage->type_puce->name;
        $is_collector = $connected_user->roles->first()->name === Roles::RECOUVREUR;
        $status = ($is_collector && ($type_puce !== Statut::PUCE_RZ)) ? Statut::EN_COURS : Statut::EFFECTUER;

        //initier le retour flotte
        $retour_flotte = new Retour_flote([
            'id_user' => $connected_user->id,
            'montant' => $montant,
            'reste' => $montant,
            'id_approvisionnement' => $flottage->id,
            'statut' => $status,
            'user_destination' => $puce_flottage->id,
            'user_source' => $puce_agent->id
        ]);
        $retour_flotte->save();

        if($status === Statut::EN_COURS) {
            //Notification du gestionnaire de flotte
            $message = 'Retour de flote éffectué par ' . $connected_user->name;
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $event = new NotificationsEvent($role->id, ['message' => $message]);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $_user) {

                if ($_user->hasRole([$role->name])) {

                    $_user->notify(new Notif_retour_flotte([
                        'data' => $retour_flotte,
                        'message' => $message
                    ]));
                }
            }
        }

        //notification de l'agent
        $user->notify(new Notif_retour_flotte([
            'data' => $retour_flotte,
            'message' => $message
        ]));

        if($status === Statut::EFFECTUER) {
            //On recupère la puce de l'agent concerné et on debite
            $puce_agent->solde = $puce_agent->solde - $montant;

            //on credite la puce de ETP concernée
            $puce_flottage->solde = $puce_flottage->solde + $montant;

            if($is_collector && $type_puce === Statut::PUCE_RZ) {
                // on augmente la dette du RZ s'il effectue le retour flotte dans sa puce
                $connected_user->dette = $connected_user->dette + $montant;
                $connected_user->save();
            }

            $puce_agent->save();
            $puce_flottage->save();
        }

        //On calcule le reste à recouvrir
        $flottage->reste = $flottage->reste - $montant;

        //On change le statut du flottage
        if ($flottage->reste == 0) $flottage->statut = Statut::EFFECTUER ;
        else $flottage->statut = Statut::EN_COURS ;

        //Enregistrer les oppérations
        $flottage->save();

        return response()->json([
            'message' => 'Retour flotte effectué avec succès',
            'status' => true,
            'data' => null
        ]);
    }

    /**
     * ////details d'un retour flotte
     */
    public function show($id)
    {
            //si le retour flotte n'existe pas
            if (!($retour_flote = Retour_flote::find($id))) {
                return response()->json(
                    [
                        'message' => "le retour flotte n'existe pas",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

            return new Retour_floteResource($retour_flote);


    }

    /**
     * ////lister les retour flotte
     */
    public function list_all()
    {
        $recoveries = Retour_flote::orderBy('created_at', 'desc')->paginate(6);

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
     * //lister tous les retour flotte
     */
    public function list_all_all()
    {
        $recoveries = Retour_flote::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvrements' => $this->recoveriesResponse($recoveries)
            ]
        ]);
    }

    /**
     * Lister les retour flotte d'un flottage
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_retour_flotte($id)
    {
        //On recupere les retour flotte
        $recoveries = Retour_flote::where('id_approvisionnement', $id)
            ->orderBy('statut', 'desc')
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
     * ////lister les retour flotte d'une puce
     */
    public function list_retour_flotte_by_sim($id)
    {
        if (!Puce::Find($id)){

            return response()->json(
                [
                    'message' => "la puce n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }

        //On recupere les retour flotte
        $retour_flotes = Retour_flote::where('user_destination', $id)
        ->orWhere('user_source', $id)
        ->get();


        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['retour_flotes' => $retour_flotes]
            ]
        );

    }

    /**
     * ////lister les retour flotte d'un Agent precis
     */
    public function list_retour_flotte_by_agent()
    {
        $user = Auth::user();

        if ($user->roles->first()->name !== Roles::AGENT && $user->roles->first()->name !== Roles::RESSOURCE){
            return response()->json([
                'message' => "Cet utilisateur n'est pas un agent/ressource",
                'status' => false,
                'data' => null
            ]);
        }

        // $id est le id du user directement
        $retour_flotes = Retour_flote::orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->get()
            ->filter(function(Retour_flote $retour_flote) use ($user){
            $demande_flote = $retour_flote->flotage->demande_flote;
            $id_user = $demande_flote->id_user;
            return $id_user == $user->id;
        });

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvrements' => $this->recoveriesResponse($retour_flotes),
                'hasMoreData' => false,
            ]
        ]);
    }

    /**
     * ////lister les retour flotte d'un Agent precis
     */
    public function list_retour_flotte_by_rz()
    {
        $user = Auth::user();

        if ($user->roles->first()->name !== Roles::RECOUVREUR){
            return response()->json([
                'message' => "Le responsable de zonne n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $recoveries = Retour_flote::where('id_user', $user->id)->orderBy('created_at', 'desc')->paginate(6);

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
     * ////approuver un retour flotte
     */
    public function approuve($id)
    {
        //si le recouvrement n'existe pas
        if (!($retour_flotte = Retour_flote::find($id))) {
            return response()->json(
                [
                    'message' => "Le retour flotte n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //on approuve le retour flotte
        $retour_flotte->statut = Statut::EFFECTUER;

        //message de reussite
        if ($retour_flotte->save())
        {
            //Notification du gestionnaire de flotte
            $role = Role::where('name', Roles::RECOUVREUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Un retour de flote approuvé']);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_retour_flotte([
                        'data' => $retour_flotte,
                        'message' => "Un retour de flote approuvé"
                    ]));
                }
            }

            return response()->json([
                'message' => 'Retour flotte apprové avec succès',
                'status' => true,
                'data' => null
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de la confirmation',
                'status'=>false,
                'data' => null
            ]);
        }
    }

    // Build recoveries return data
    private function recoveriesResponse($recoveries)
    {
        $returnedRecoveries = [];

        foreach($recoveries as $recovery)
        {
            $puce_agent = $recovery->puce_source;
            $agent = $puce_agent->agent;
            $user = $agent->user;
            $recouvreur = $recovery->user;
            $puce_flottage = $recovery->puce_destination;

            $returnedRecoveries[] = [
                'recouvrement' => $recovery,
                'user' => $user,
                'agent' => $agent,
                'recouvreur' => $recouvreur,
                'puce_agent' => $puce_agent,
                'puce_flottage' => $puce_flottage,
                'operateur' => $puce_flottage->flote
            ];
        }

        return $returnedRecoveries;
    }
}

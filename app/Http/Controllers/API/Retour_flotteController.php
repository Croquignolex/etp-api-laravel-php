<?php

namespace App\Http\Controllers\API;

use App\Puce;
use App\User;
use App\Role;
use App\Agent;
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
     * ////Faire un retour de flotte
     */
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

        //On verifi si le flottage passée existe réellement
        if (!Approvisionnement::find($request->id_flottage)) {
            return response()->json(
                [
                    'message' => "Le flottage n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //On verifi que le montant n'est pas supperieur au montant demandé
        $flottage = Approvisionnement::find($request->id_flottage);
        if ($flottage->reste < $request->montant) {
            return response()->json([
                'message' => "Vous essayez de recouvrir plus d'argent que prevu",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi si la puce passée appartien à l'agent concerné
        //L'agent concerné
        $user = User::Find($flottage->demande_flote->id_user);
        $agent = Agent::Where('id_user', $user->id)->first();
        $puce_agent = Puce::Find($request->puce_agent);
        $puce_flottage = Puce::Find($request->puce_flottage);

        //On verifi que le montant n'est pas supperieur au montant demandé
        if ($puce_agent == null || $puce_flottage == null) {
            return response()->json([
                'message' => "'Lune des puce n'existe pas",
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
        if ($puce_flottage->flote->nom != $puce_agent->flote->nom) {
            return response()->json([
                'message' => "Vous devez renvoyer la flotte à une puce du même opérateur",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi que le retour flote est fait ver une puce apte à flotter
        $type_puce = $puce_flottage->type_puce->name;
        if ($type_puce != \App\Enums\Statut::FLOTTAGE && $type_puce != \App\Enums\Statut::FLOTTAGE_SECONDAIRE) {
            return response()->json([
                'message' => "Vous ne pouvez renvoyer la flotte qu'à une puce agent",
                'status' => false,
                'data' => null
            ]);
        }

        //On recupère les données validés

        //enregistrer le recu
        $recu = null;
        if ($request->hasFile('recu') && $request->file('recu')->isValid()) {
            $recu = $request->recu->store('recu');
        }
        $montant = $request->montant;

        //recouvreur
        $user = Auth::user();

        $is_manager = $user->roles->first()->name === Roles::GESTION_FLOTTE;

        //initier le retour flotte
        $retour_flotte = new Retour_flote([
            'id_user' => $user->id,
            'reference' => null,
            'montant' => $montant,
            'reste' => $montant,
            'id_approvisionnement' => $request->id_flottage,
            'statut' => $is_manager ? Statut::EFFECTUER : Statut::EN_COURS,
            'user_destination' => $puce_flottage->id,
            'user_source' => $puce_agent->id
        ]);

        if ($retour_flotte->save()) {

            //Notification du gestionnaire de flotte
            $role = Role::where('name', Roles::GESTION_FLOTTE)->orWhere('name', Roles::RECOUVREUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Nouveau retour de flote']);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $_user) {

                if ($_user->hasRole([$role->name])) {

                    $_user->notify(new Notif_retour_flotte([
                        'data' => $retour_flotte,
                        'message' => "Nouveau retour de flote"
                    ]));
                }
            }

            //on credite la puce de ETP concernée
            $puce_flottage->solde = $puce_flottage->solde + $montant;
            $puce_flottage->save();

            //On recupère la puce de l'agent concerné et on debite
            $puce_agent->solde = $puce_agent->solde - $montant;
            $puce_agent->save();

            //On credite la caisse de l'Agent pour le remboursement de la flotte recu, ce qui implique qu'il rembource ses detes à ETP
            //Caisse de l'agent concerné
            $caisse = $user->caisse->first();
            $caisse->solde = $caisse->solde + $montant;
            $caisse->save();

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

            return response()->json(
                [
                    'message' => 'Retour flotte éffectué avec succès',
                    'status' => true,
                    'data' => null
                ]
            );
        }else {

            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors du destockage',
                    'status'=>false,
                    'data' => null
                ]
            );
        }
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
     * ////lister les retour flotte d'un flottage
     */
    public function list_retour_flotte($id)
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

        //On recupere les retour flotte
        $retour_flotes = Retour_flote::where('id_approvisionnement', $id)->get();


        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['retour_flotes' => $retour_flotes]
            ]
        );

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
    public function list_retour_flotte_by_agent($id)
    {
        // $id est le id du user directement
        $retour_flotes = Retour_flote::get()->filter(function(Retour_flote $retour_flote) use ($id){
            $demande_flote =$retour_flote->flotage->demande_flote;
            $id_user = $demande_flote->id_user;
            return $id_user == $id;
        });

        $retours_flotes = [];

        foreach($retour_flotes as $retour_flote) {

            //recuperer le flottage correspondant
            $flottage = Approvisionnement::find($retour_flote->id_approvisionnement);

            //recuperer celui qui a éffectué le retour flotte
            $user = User::find($retour_flote->flotage->demande_flote->id_user);
            $agent = Agent::Where('id_user', $user->id)->first();

            $recouvreur = User::find($retour_flote->id_user);

            $puce_agent = Puce::find($retour_flote->user_source);
            $puce_flottage = Puce::find($retour_flote->user_destination);

            $retours_flotes[] = [
                'recouvrement' => $retour_flote,
                'flottage' => $flottage,
                'user' => $user,
                'agent' => $agent,
                'recouvreur' => $recouvreur,
                'puce_agent' => $puce_agent,
                'puce_flottage' => $puce_flottage,
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['recouvrements' => $retours_flotes]
            ]
        );
    }

    /**
     * ////lister les retour flotte d'un Agent precis
     */
    public function list_retour_flotte_by_rz($id)
    {
        if (!$user = User::find($id)){

            return response()->json(
                [
                    'message' => "le Responsable de zonne n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }

        $retour_flotes = Retour_flote::where('id_user', $id)->get();

        $retours_flotes = [];

        foreach($retour_flotes as $retour_flote) {

            //recuperer le flottage correspondant
            $flottage = Approvisionnement::find($retour_flote->id_approvisionnement);

            //recuperer celui qui a éffectué le retour flotte
            $user = User::find($retour_flote->flotage->demande_flote->id_user);
            $agent = Agent::Where('id_user', $user->id)->first();

            $recouvreur = User::find($retour_flote->id_user);

            $puce_agent = Puce::find($retour_flote->user_source);
            $puce_flottage = Puce::find($retour_flote->user_destination);

            $retours_flotes[] = [
                'recouvrement' => $retour_flote,
                'flottage' => $flottage,
                'user' => $user,
                'agent' => $agent,
                'recouvreur' => $recouvreur,
                'puce_agent' => $puce_agent,
                'puce_flottage' => $puce_flottage,
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['recouvrements' => $retours_flotes]
            ]
        );
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
            $puce_agent = Puce::find($recovery->user_source);

            $agent = $puce_agent->agent;
            $user = $agent->user;

            $recouvreur = User::find($recovery->id_user);

            $puce_flottage = Puce::find($recovery->user_destination);

            $returnedRecoveries[] = [
                'recouvrement' => $recovery,
                'user' => $user,
                'agent' => $agent,
                'recouvreur' => $recouvreur,
                'puce_agent' => $puce_agent,
                'puce_flottage' => $puce_flottage,
            ];
        }

        return $returnedRecoveries;
    }
}

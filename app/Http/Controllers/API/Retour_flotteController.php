<?php

namespace App\Http\Controllers\API;

use App\Puce;
use App\User;
use App\Transaction;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Retour_flote;
use App\Enums\Transations;
use App\Approvisionnement;
use Illuminate\Http\Request;
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
    // RESPONSABLE DE ZONE
    // SUPERVISEUR
    public function retour(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'puce_agent' => ['required', 'numeric'],
            'puce_flottage' => ['required', 'numeric'],
            'id_flottage' => ['required', 'numeric'],
            'montant' => ['required', 'numeric'],
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
                'message' => "L'une des puces n'existe pas",
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

        //On recupère la puce de l'agent concerné et on debite
        $puce_agent->solde = $puce_agent->solde - $montant;
        $puce_agent->save();

        $message = 'Retour de flote éffectué par ' . $connected_user->name;
        if($status === Statut::EN_COURS) {
            //Notification du gestionnaire de flotte
            $users = User::all();
            foreach ($users as $_user) {
                if ($_user->hasRole([Roles::GESTION_FLOTTE])) {
                    $_user->notify(new Notif_retour_flotte([
                        'data' => $retour_flotte,
                        'message' => $message
                    ]));
                }
            }
        } else if($status === Statut::EFFECTUER) {
            //on credite la puce de ETP concernée
            $puce_flottage->solde = $puce_flottage->solde + $montant;
            $puce_flottage->save();

            if($is_collector && $type_puce === Statut::PUCE_RZ) {
                // on augmente la dette du RZ s'il effectue le retour flotte dans sa puce
                $connected_user->dette = $connected_user->dette + $montant;
                $connected_user->save();
            }

            // Garder la transaction éffectué par la GF
            Transaction::create([
                'type' => Transations::RETOUR_FLOTTE,
                'in' => $retour_flotte->montant,
                'out' => 0,
                'id_operator' => $puce_flottage->flote->id,
                'id_left' => $puce_flottage->id,
                'id_right' => $puce_agent->id,
                'balance' => $puce_flottage->solde,
                'id_user' => $connected_user->id,
            ]);
        }

        //notification de l'agent
        $user->notify(new Notif_retour_flotte([
            'data' => $retour_flotte,
            'message' => $message
        ]));

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
     * Retour de flotte sans flottage prélable
     */
    // GESTIONNAIRE DE FLOTTE
    public function retour_sans_flottage(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'puce_agent' => ['required', 'numeric'],
            'puce_flottage' => ['required', 'numeric'],
            'montant' => ['required', 'numeric'],
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

        $puce_agent = Puce::find($request->puce_agent);
        $puce_flottage = Puce::find($request->puce_flottage);
        $agent = $puce_agent->agent;
        $user = $agent->user;

        //On verifi que le montant n'est pas supperieur au montant demandé
        if ($puce_agent == null || $puce_flottage == null) {
            return response()->json([
                'message' => "L'une des puces n'existe pas",
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

        //initier le retour flotte
        $retour_flotte = new Retour_flote([
            'id_user' => $connected_user->id,
            'montant' => $montant,
            'reste' => $montant,
            'statut' => Statut::EFFECTUER,
            'user_destination' => $puce_flottage->id,
            'user_source' => $puce_agent->id
        ]);
        $retour_flotte->save();

        //On recupère la puce de l'agent concerné et on debite
        $puce_agent->solde = $puce_agent->solde - $montant;
        $puce_agent->save();

        $message = 'Retour de flote éffectué par ' . $connected_user->name;

        //on credite la puce de ETP concernée
        $puce_flottage->solde = $puce_flottage->solde + $montant;
        $puce_flottage->save();

        // Garder la transaction éffectué par la GF
        Transaction::create([
            'type' => Transations::RETOUR_FLOTTE,
            'in' => $retour_flotte->montant,
            'out' => 0,
            'id_operator' => $puce_flottage->flote->id,
            'id_left' => $puce_flottage->id,
            'id_right' => $puce_agent->id,
            'balance' => $puce_flottage->solde,
            'id_user' => $connected_user->id,
        ]);

        //notification de l'agent
        $user->notify(new Notif_retour_flotte([
            'data' => $retour_flotte,
            'message' => $message
        ]));

        return response()->json([
            'message' => 'Retour flotte effectué avec succès',
            'status' => true,
            'data' => [
                'recouvrement' => $retour_flotte,
                'user' => $user,
                'agent' => $agent,
                'recouvreur' => $retour_flotte->user,
                'puce_agent' => $puce_agent,
                'puce_flottage' => $puce_flottage,
                'operateur' => $puce_flottage->flote
            ]
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
     * Lister les retour flotte
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_all()
    {
        $recoveries = Retour_flote::orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvrements' => $this->recoveriesResponse($recoveries->items()),
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
     * Lister les retour flotte d'un Agent precis
     */
    // RESPONSABLE DE ZONE
    public function list_retour_flotte_by_rz()
    {
        $user = Auth::user();

        $recoveries = Retour_flote::where('id_user', $user->id)
            ->orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvrements' => $this->recoveriesResponse($recoveries->items()),
                'hasMoreData' => $recoveries->hasMorePages(),
            ]
        ]);
    }

    /**
     * Approuver un retour flotte
     */
    // GESTIONNAIRE DE FLOTTE
    public function approuve($id)
    {
        //si le recouvrement n'existe pas
        $retour_flotte = Retour_flote::find($id);
        if (is_null($retour_flotte)) {
            return response()->json([
                'message' => "Le retour flotte n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($retour_flotte->statut === Statut::EFFECTUER) {
            return response()->json([
                'message' => "Le retour flotte a déjà été confirmé",
                'status' => false,
                'data' => null
            ]);
        }

        //on approuve le retour flotte
        $retour_flotte->statut = Statut::EFFECTUER;

        $connected_user = Auth::user();
        $collector = $retour_flotte->user;

        //On recupère la puce de l'agent concerné et on debite
        $montant = $retour_flotte->montant;

        //on credite la puce de ETP concernée
        $puce_flottage = $retour_flotte->puce_destination;
        $puce_flottage->solde = $puce_flottage->solde + $montant;
        $puce_agent = $retour_flotte->puce_source;

        $puce_flottage->save();
        $retour_flotte->save();

        // Garder la transaction éffectué par la GF
        Transaction::create([
            'type' => Transations::RETOUR_FLOTTE,
            'in' => $retour_flotte->montant,
            'out' => 0,
            'id_operator' => $puce_flottage->flote->id,
            'id_left' => $puce_flottage->id,
            'id_right' => $puce_agent->id,
            'balance' => $puce_flottage->solde,
            'id_user' => $connected_user->id,
        ]);

        if($collector) {
            // On notifie si on est en presence d'un RZ
            $message = "Retour flotte à été apprové par " . $connected_user->name;
            $collector->notify(new Notif_retour_flotte([
                'data' => $retour_flotte,
                'message' => $message
            ]));
        }

        return response()->json([
            'message' => 'Retour flotte apprové avec succès',
            'status' => true,
            'data' => null
        ]);
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

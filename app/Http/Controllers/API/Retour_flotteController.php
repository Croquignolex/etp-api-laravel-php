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

class Retour_flotteController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $agent = Roles::AGENT;
        $comptable = Roles::COMPATBLE;
        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $controlleur = Roles::CONTROLLEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$agent|$controlleur|$comptable");
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

        // Vérification de la validation éffective
        if ($flottage->statut === Statut::ANNULE) {
            return response()->json([
                'message' => "Le flottage a déjà été annulé",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($flottage->statut === Statut::EFFECTUER) {
            return response()->json([
                'message' => "Le flottage a déjà été confirmé",
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
            'reference' => $user->id,
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
     * Retour de flotte groupee
     */
    // GESTIONNAIRE DE FLOTTE
    // RESPONSABLE DE ZONE
    // SUPERVISEUR
    public function retour_groupee(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'puce_agent' => ['required', 'numeric'],
            'puce_flottage' => ['required', 'numeric'],
            'ids_flottage' => ['required'],
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
        //On verifi que le montant n'est pas supperieur au montant demandé
        if ($puce_agent == null || $puce_flottage == null) {
            return response()->json([
                'message' => "L'une des puces n'existe pas",
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

        foreach ($request->ids_flottage as $id){
            //On verifi si le flottage passée existe réellement
            $flottage = Approvisionnement::find($id);
            $user = $flottage->demande_flote->user;

            $montant_retour = $montant;

            if($montant != 0) {
                if ($montant > $flottage->reste) {
                    $montant_retour = $flottage->reste;
                }

                $montant = $montant - $montant_retour;

                if(
                    !is_null($flottage) &&
                    $flottage->statut !== Statut::ANNULE &&
                    $flottage->statut !== Statut::EFFECTUER
                ) {
                    $connected_user = Auth::user();
                    $type_puce = $puce_flottage->type_puce->name;
                    $is_collector = $connected_user->roles->first()->name === Roles::RECOUVREUR;
                    $status = ($is_collector && ($type_puce !== Statut::PUCE_RZ)) ? Statut::EN_COURS : Statut::EFFECTUER;

                    //initier le retour flotte
                    $retour_flotte = new Retour_flote([
                        'id_user' => $connected_user->id,
                        'montant' => $montant_retour,
                        'reste' => $montant_retour,
                        'reference' => $user->id,
                        'id_approvisionnement' => $flottage->id,
                        'statut' => $status,
                        'user_destination' => $puce_flottage->id,
                        'user_source' => $puce_agent->id
                    ]);
                    $retour_flotte->save();

                    //On recupère la puce de l'agent concerné et on debite
                    $puce_agent->solde = $puce_agent->solde - $montant_retour;
                    $puce_agent->save();

                    if($status === Statut::EFFECTUER) {
                        //on credite la puce de ETP concernée
                        $puce_flottage->solde = $puce_flottage->solde + $montant_retour;
                        $puce_flottage->save();

                        if($is_collector && $type_puce === Statut::PUCE_RZ) {
                            // on augmente la dette du RZ s'il effectue le retour flotte dans sa puce
                            $connected_user->dette = $connected_user->dette + $montant_retour;
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

                    //On calcule le reste à recouvrir
                    $flottage->reste = $flottage->reste - $montant_retour;

                    //On change le statut du flottage
                    if ($flottage->reste == 0) $flottage->statut = Statut::EFFECTUER ;
                    else $flottage->statut = Statut::EN_COURS ;

                    //Enregistrer les oppérations
                    $flottage->save();
                }
            }
        }

        return response()->json([
            'message' => 'Retour flotte groupé effectué avec succès',
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
            'id_agent' => ['required', 'numeric'],
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

        $user = User::find($request->id_agent);

        //On verifi que le montant n'est pas supperieur au montant demandé
        if ($puce_agent == null || $puce_flottage == null) {
            return response()->json([
                'message' => "L'une des puces n'existe pas",
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

        //initier le retour flotte
        $retour_flotte = new Retour_flote([
            'id_user' => $connected_user->id,
            'montant' => $montant,
            'reste' => $montant,
            'reference' => $user->id,
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
                'agent' => $user->agent->first(),
                'recouvreur' => $retour_flotte->user,
                'puce_agent' => $puce_agent,
                'puce_flottage' => $puce_flottage,
                'operateur' => $puce_flottage->flote
            ]
        ]);
    }

    /**
     * Lister les retour flotte
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_all()
    {
        $recoveries = Retour_flote::orderBy('created_at', 'desc')->paginate(9);

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
     * Lister les retour flotte groupee
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_all_groupee()
    {
        $recoveries = Retour_flote::where("statut", Statut::EN_COURS)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'recouvrements' => $this->recoveriesResponse($recoveries),
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
     * ////lister les retour flotte d'un Agent precis
     */
    // AGENT
    // RESSOURCE
    public function list_retour_flotte_by_agent()
    {
        $user = Auth::user();

        // $id est le id du user directement
        $retour_flotes = Retour_flote::orderBy('created_at', 'desc')
            ->get()
            ->filter(function(Retour_flote $recovery) use ($user) {
                return $recovery->agent_user->id == $user->id;
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
        if ($retour_flotte->statut === Statut::ANNULE) {
            return response()->json([
                'message' => "Le retour flotte a déjà été annulé",
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

    /**
     * Approuver un retour flotte groupee
     */
    // GESTIONNAIRE DE FLOTTE
    public function approuve_groupee(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'ids' => ['required']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        foreach ($request->ids as $id){
            $retour_flotte = Retour_flote::find($id);

            if(
                !is_null($retour_flotte) &&
                $retour_flotte->statut !== Statut::ANNULE &&
                $retour_flotte->statut !== Statut::EFFECTUER
            ) {
                //on approuve le retour flotte
                $retour_flotte->statut = Statut::EFFECTUER;

                $connected_user = Auth::user();

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
            }
        }

        return response()->json([
            'message' => 'Retour flotte groupé approuvé avec succès',
            'status' => true,
            'data' => null
        ]);
    }

    /**
     * Annuler le retour flotte
     */
    // RESPONSABLE DE ZONE
    public function annuler_retour_flotte($id)
    {
        $retour_flotte = Retour_flote::find($id);
        //si le destockage n'existe pas
        if (is_null($retour_flotte)) {
            return response()->json([
                'message' => "Le retour flotte n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($retour_flotte->statut === Statut::ANNULE) {
            return response()->json([
                'message' => "Le retour flotte a déjà été annulé",
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

        $montant = $retour_flotte->montant;
        $flottage = $retour_flotte->flotage;
        $puce_agent = $retour_flotte->puce_source;

        //on approuve le flottage
        $retour_flotte->statut = Statut::ANNULE;

        //On recupère la puce de l'agent concerné et on debite
        $puce_agent->solde = $puce_agent->solde + $montant;
        $puce_agent->save();

        //On calcule le reste à recouvrir
        $flottage->reste = $flottage->reste + $montant;

        //On change le statut du flottage
        if ($flottage->reste === $flottage->montant) $flottage->statut = Statut::EN_ATTENTE ;
        else $flottage->statut = Statut::EN_COURS ;

        //Enregistrer les oppérations
        $flottage->save();
        $retour_flotte->save();

        return response()->json([
            'message' => "Retour flotte annulé avec succès",
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

            $user = $recovery->agent_user;
            $agent = $user->agent->first();

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

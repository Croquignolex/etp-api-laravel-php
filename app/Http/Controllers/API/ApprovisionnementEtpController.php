<?php

namespace App\Http\Controllers\API;

use App\Puce;
use App\User;
use App\Role;
use App\Agent;
use App\Caisse;
use App\Destockage;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Demande_destockage;
use Illuminate\Http\Request;
use App\Events\NotificationsEvent;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Destockage as Notif_destockage;
use App\Http\Resources\Destockage as DestockageResource;

class ApprovisionnementEtpController extends Controller
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
     * ////traiter une demande de destockage
     */
    public function traiter_demande(Request $request)
    {
            // Valider données envoyées
            $validator = Validator::make($request->all(), [
                'montant' => ['required', 'Numeric'],
                'id_demande' => ['required', 'Numeric']
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

            //si la demande n'existe pas
            if (!($demande = Demande_destockage::find($request->id_demande))) {
                return response()->json(
                    [
                        'message' => "cette demande n'existe pas",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

            //si la demande est revoquée
            if ($demande->statut == Statut::DECLINE) {
                return response()->json(
                    [
                        'message' => "cette demande est revocquée",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

            //on controle le montant
            if ($request->montant > $demande->reste) {
                return response()->json(
                    [
                        'message' => "vous ne pouvez pas destocker plus que prévu",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

            //on reduit le prix de la demande en fonction de ce qu'on veut destocker
            $demande->reste = $demande->reste - $request->montant;

            //on change le statut
            if ($demande->reste == 0) {
                $demande->statut = Statut::COMPLETER;
            }

            //message de reussite
            if ($demande->save()) {

                //Broadcast Notification
                $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
                $event = new NotificationsEvent($role->id, ['message' => 'Une demande traitée']);
                broadcast($event)->toOthers();

                //Database Notification
                $users = User::all();
                foreach ($users as $user) {

                    if ($user->hasRole([$role->name])) {

                        $user->notify(new Notif_destockage([
                            'data' => $demande,
                            'message' => "Une demande en cours de traitement"
                        ]));
                    }
                }

                //Database Notification
                User::find($demande->id_user)->notify(new Notif_destockage(['message' => "Votre demande est entrain d'etre traitée"]));

                //Reponse
                return response()->json(
                    [
                        'message' => "demande traitée",
                        'status' => true,
                        'data' => $demande
                    ]
                );
            }
    }

    /**
     * //////revoquer une demande.
     */
    public function revoque_demande(Request $request)
    {
            // Valider données envoyées
            $validator = Validator::make($request->all(), [
                'note' => ['required', 'string'],
                'id_demande' => ['required', 'Numeric']
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

            //si la demande n'existe pas
            if (!($demande = Demande_destockage::find($request->id_demande))) {
                return response()->json(
                    [
                        'message' => "cette demande n'existe pas",
                        'status' => false,
                        'data' => null
                    ]
                );
            }



            //on change le statut
            $demande->statut = Statut::DECLINE;
            $demande->note = $request->note;


            //message de reussite
            if ($demande->save()) {

                //Broadcast Notification
                $role = Role::where('name', Roles::RECOUVREUR)->first();
                $event = new NotificationsEvent($role->id, ['message' => 'Une demande revoquée']);
                broadcast($event)->toOthers();

                //Database Notification
                $users = User::all();
                foreach ($users as $user) {

                    if ($user->hasRole([$role->name])) {

                        $user->notify(new Notif_destockage([
                            'data' => $demande,
                            'message' => "Une demande Revoquée"
                        ]));
                    }
                }

                //Database Notification
                User::find($demande->id_user)->notify(new Notif_destockage(['message' => "Votre demande a été Revoquée"]));


                return response()->json(
                    [
                        'message' => "demande revocquée",
                        'status' => true,
                        'data' => $demande
                    ]
                );
            }

    }

    /**
     * ////Effectuer un destockage
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'max:255'], //BY_AGENT, BY_DIGIT_PARTNER or BY_BANK
            'fournisseur' => ['nullable', 'string', 'max:255'], // si le type est BY_DIGIT_PARTNER ou BY_BANK
            'id_agent' => ['nullable', 'Numeric'],       // obligatoire si le type est BY_AGENT
            'id_puce' => ['required', 'Numeric'],
            'recu' => ['required', 'file', 'max:10000'],
            'montant' => ['required', 'Numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        //au cas ou le type est BY_AGENT, on est sencé recevoir l'id de l'agent. on verifi que l'id recu est bien un Agent
        if (isset($request->id_agent)) {
            //on verifi si l'agent existe
            if (!($agent = User::find($request->id_agent)->agent->first())) {
                return response()->json([
                    'message' => "Agent invalide",
                    'status' => false,
                    'data' => null
                ]);
            }
        }

        //On recupère les données validés
        //enregistrer le recu
        $recu = null;
        if ($request->hasFile('recu') && $request->file('recu')->isValid()) {
            $recu = $request->recu->store('recu');
        }
        $type = $request->type;
        $fournisseur = $request->fournisseur;
        $id_agent = $request->id_agent;
        $id_puce = $request->id_puce;
        $montant = $request->montant;
        $user = Auth::user();
        $user_role = $user->roles->first()->name;
        $type_puce = Puce::find($id_puce)->type_puce->name;

        $statut = (($user_role === Roles::RECOUVREUR) && ($type_puce !== Statut::PUCE_RZ)) ? Statut::EN_COURS : Statut::EFFECTUER;

        //initier le destockage encore appelé approvisionnement de ETP
        $destockage = new Destockage([
            'id_recouvreur' => $user->id,
            'type' => $type,
            'id_puce' => $id_puce,
            'id_agent' => isset($request->id_agent) ? $id_agent : null,
            'fournisseur' => isset($request->fournisseur) ? $fournisseur : null,
            'recu' => $recu,
            'reference' => null,
            'statut' => $statut,
            'note' => null,
            'montant' => $montant
        ]);

        if ($destockage->save())
        {
            //Notification
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $role2 = Role::where('name', Roles::SUPERVISEUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Nouvel approvisionnement de ETP']);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {
                if ($user->hasRole([$role->name]) || $user->hasRole([$role2->name])) {

                    $user->notify(new Notif_destockage([
                        'data' => $destockage,
                        'message' => "Nouvel approvisionnement de ETP"
                    ]));
                }
            }

            //la puce de ETP concernée et on credite
            $puce_etp = Puce::find($request->id_puce);
            $puce_etp->solde = $puce_etp->solde + $montant;
            $puce_etp->save();

            if (isset($request->id_agent))
            {
                //on notifie l'agent
                $agent->user->notify(new Notif_destockage([
                    'data' => $destockage,
                    'message' => "Nouveau déstockage"
                ]));
            }

            $connected_user = Auth::user();

            //la caisse de l'utilisateur connecté
            $connected_caisse = Caisse::where('id_user', $connected_user->id)->first();

            //mise à jour de la caisse de l'utilisateur qui effectue l'oppération
            if ($connected_user->hasRole([Roles::GESTION_FLOTTE])) {
                $connected_caisse->solde = $connected_caisse->solde - $montant;
            }else {
                $connected_caisse->solde = $connected_caisse->solde + $montant;
            }
            $connected_caisse->save();

            $agent = $destockage->id_agent === null ? $destockage->id_agent : User::find($destockage->id_agent)->agent->first();
            $user = $destockage->id_agent === null ? $destockage->id_agent : User::find($destockage->id_agent);

            return response()->json([
                'message' => "Déstockage effectué avec succès",
                'status' => true,
                'data' => [
                    'id' => $destockage->id,
                    'recu' => $destockage->recu,
                    'statut' => $destockage->statut,
                    'montant' => $destockage->montant,
                    'created_at' => $destockage->created_at,
                    'recouvreur' => User::find($destockage->id_recouvreur),
                    'puce' => $destockage->puce,
                    'fournisseur' => $destockage->fournisseur,
                    'agent' => $agent,
                    'user' => $user,
                ]
            ]);

        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors du destockage',
                'status'=>false,
                'data' => null
            ]);
        }
    }

    /**
     * ////approuver une demande de destockage
     */
    public function approuve($id)
    {
        //si le destockage n'existe pas
        if (!($destockage = Destockage::find($id))) {
            return response()->json([
                'message' => "le destockage n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //on approuve le destockage
        $destockage->statut = Statut::EFFECTUER;

        //message de reussite
        if ($destockage->save()) {
            $destockages = Destockage::where('type', Statut::BY_AGENT)->get();

            //Notification
            $role = Role::where('name', Roles::RECOUVREUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Approvisionnement Approvée']);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_destockage([
                        'data' => $destockage,
                        'message' => "Approvisionnement Approvée"
                    ]));
                }
            }

            return response()->json([
                'message' => "Déstockage apprové avec succès",
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

    /**
     * ////approuver un approvisionnement
     */
    public function approuve_approvisionnement($id)
    {
        //si le destockage n'existe pas
        if (!($destockage = Destockage::find($id))) {
            return response()->json([
                'message' => "Le destockage n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //on approuve le destockage
        $destockage->statut = Statut::EFFECTUER;

        //message de reussite
        if ($destockage->save()) {

            //Notification
            $role = Role::where('name', Roles::RECOUVREUR)->first();
            $event = new NotificationsEvent($role->id, ['message' => 'Approvisionnement Approvée']);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_destockage([
                        'data' => $destockage,
                        'message' => "Approvisionnement Approvée"
                    ]));
                }
            }

            return response()->json([
                'message' => "Approvisionnement apprové avec succès",
                'status' => true,
                'data' => null
            ]);
         }else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de la confirmation',
                'status'=>false,
                'data' => null
            ]);
        }
    }

    /**
     * ////details d'une demande de destockage
     */
    public function detail($id)
    {

            //si le destockage n'existe pas
            if (!($destockage = Destockage::find($id))) {
                return response()->json(
                    [
                        'message' => "le destockage n'existe pas",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

            return new DestockageResource($destockage);


    }

    /**
     * ////lister les approvisionnements
     */
    public function list_all()
    {
        $destockages = Destockage::where('type', Statut::BY_DIGIT_PARTNER)
            ->orWhere('type', Statut::BY_BANK)
            ->orderBy('created_at', 'desc')->paginate(6);

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'destockages' => DestockageResource::collection($destockages->items()),
                'hasMoreData' => $destockages->hasMorePages(),
            ]
        ]);
    }

    /**
     * ////lister les approvisionnements par un responsable de zone
     */
    public function list_all_collector()
    {
        $user = Auth::user();
        $userRole = $user->roles->first()->name;

        if($userRole === Roles::RECOUVREUR) {
            $destockages = Destockage::where('id_recouvreur', $user->id)
                ->where(function($query) {
                    $query->where('type', Statut::BY_DIGIT_PARTNER);
                    $query->orWhere('type', Statut::BY_BANK);
                })
                ->orderBy('created_at', 'desc')->paginate(6);

            return response()->json([
                'message' => "",
                'status' => true,
                'data' => [
                    'destockages' => DestockageResource::collection($destockages->items()),
                    'hasMoreData' => $destockages->hasMorePages(),
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

    // BY_AGENT
    /**
     * ////lister les destockages
     */
    public function list_all_destockage()
    {
        $destockages = Destockage::where('type', Statut::BY_AGENT)->orderBy('created_at', 'desc')->paginate(6);

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'destockages' => DestockageResource::collection($destockages->items()),
                'hasMoreData' => $destockages->hasMorePages(),
            ]
        ]);
    }

    /**
     * ////lister les destockages par un responsable de zone
     */
    public function list_all_destockage_collector()
    {
        $user = Auth::user();
        $userRole = $user->roles->first()->name;

        if($userRole === Roles::RECOUVREUR) {
            $destockages = Destockage::where('type', Statut::BY_AGENT)->where('id_recouvreur', $user->id)->orderBy('created_at', 'desc')->paginate(6);

            return response()->json([
                'message' => "",
                'status' => true,
                'data' => [
                    'destockages' => DestockageResource::collection($destockages->items()),
                    'hasMoreData' => $destockages->hasMorePages(),
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
     * ////lister les destockages par un agent
     */
    public function list_all_destockage_agent()
    {
        $user = Auth::user();
        $userRole = $user->roles->first()->name;

        if($userRole === Roles::AGENT || $userRole === Roles::RESSOURCE) {
            $destockages = Destockage::where('type', Statut::BY_AGENT)
                ->where('id_agent', $user->id)
                ->orderBy('created_at', 'desc')->paginate(6);

            return response()->json([
                'message' => "",
                'status' => true,
                'data' => [
                    'destockages' => DestockageResource::collection($destockages->items()),
                    'hasMoreData' => $destockages->hasMorePages(),
                ]
            ]);
        } else {
            return response()->json([
                'message' => "Cet utilisateur n'est pas un agent/ressource",
                'status' => false,
                'data' => null
            ]);
        }
    }

}

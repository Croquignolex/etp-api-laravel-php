<?php

namespace App\Http\Controllers\API;

use App\Notifications\Flottage as Notif_flottage;
use App\Puce;
use App\User;
use App\Role;
use App\Caisse;
use App\Destockage;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Demande_destockage;
use Illuminate\Database\Eloquent\Model;
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
     * ////Effectuer un destockage
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'max:255'], //BY_AGENT, BY_DIGIT_PARTNER or BY_BANK
            'id_fournisseur' => ['nullable', 'Numeric'], // si le type est BY_DIGIT_PARTNER ou BY_BANK
            'id_agent' => ['nullable', 'Numeric'],       // obligatoire si le type est BY_AGENT
            'id_puce' => ['required', 'Numeric'],
            'montant' => ['required', 'Numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $type = $request->type;
        $fournisseur = $request->id_fournisseur;
        $id_agent = $request->id_agent;
        $id_puce = $request->id_puce;
        $montant = $request->montant;

        $puce_emetrice = Puce::find($id_puce);
        $type_puce = $puce_emetrice->type_puce->name;

        $connected_user = Auth::user();
        $user_role = $connected_user->roles->first()->name;

        $statut = (($user_role === Roles::RECOUVREUR) && ($type_puce !== Statut::PUCE_RZ)) ? Statut::EN_COURS : Statut::EFFECTUER;

        //initier le destockage encore appelé approvisionnement de ETP
        $destockage = new Destockage([
            'id_recouvreur' => $connected_user->id,
            'type' => $type,
            'id_puce' => $id_puce,
            'id_agent' => isset($request->id_agent) ? $id_agent : null,
            'id_fournisseur' => isset($request->id_fournisseur) ? $fournisseur : null,
            'statut' => $statut,
            'montant' => $montant
        ]);
        $destockage->save();

        //Notification
        if($type === Statut::BY_AGENT && $statut === Statut::EN_COURS) {
            $message = "Déstockage éffectué par " . $connected_user->name;
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $event = new NotificationsEvent($role->id, ['message' => $message]);
            broadcast($event)->toOthers();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {
                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_destockage([
                        'data' => $destockage,
                        'message' => $message
                    ]));
                }
            }

            //Database Notification de l'agent
            User::find($id_agent)->notify(new Notif_flottage([
                'message' => $message,
                'data' => $destockage
            ]));
        }

        // Implication d'un déstockage avec effectué directement
        if($type === Statut::BY_AGENT && $statut === Statut::EFFECTUER) {
            // Flotte ajouté dans les puce emetrice
            $puce_emetrice->solde = $puce_emetrice->solde + $montant;

            // Dimuner les especes dans la caisse
            $connected_caisse = Caisse::where('id_user', $connected_user->id)->first();
            $connected_caisse->solde = $connected_caisse->solde - $montant;

            if($user_role === Roles::RECOUVREUR && $type_puce === Statut::FLOTTAGE) {
                // Si RZ et puce GF (reduire la dette)
                $connected_user->dette = $connected_user->dette - $montant;
                $connected_user->save();
            }

            $puce_emetrice->save();
            $connected_caisse->save();
        }

        $user = $destockage->id_agent === null ? $destockage->id_agent : User::find($destockage->id_agent);
        $agent = $user === null ? $user : $user->agent->first();

        return response()->json([
            'message' => "Déstockage effectué avec succès",
            'status' => true,
            'data' => [
                'id' => $destockage->id,
                'statut' => $statut,
                'montant' => $montant,
                'created_at' => $destockage->created_at,
                'recouvreur' => $connected_user,
                'puce' => $puce_emetrice,
                'fournisseur' => $fournisseur,
                'agent' => $agent,
                'user' => $user,
                'operateur' => $puce_emetrice->flote
            ]
        ]);
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
                'message' => "L'approvisionnement n'existe pas",
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
        $destockages = Destockage::where('type', Statut::BY_AGENT)
            ->orderBy('statut', 'desc')
            ->orderBy('created_at', 'desc')
            ->paginate(6);

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

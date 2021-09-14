<?php

namespace App\Http\Controllers\API;

use App\Puce;
use App\User;
use App\Role;
use App\Agent;
use App\Caisse;
use App\Vendor;
use App\Movement;
use App\Type_puce;
use App\Destockage;
use App\Enums\Roles;
use App\Transaction;
use App\Enums\Statut;
use App\Enums\Transations;
use App\Demande_destockage;
use App\Zone;
use Illuminate\Http\Request;
use App\Events\NotificationsEvent;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Flottage as Notif_flottage;
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
        $comptable = Roles::COMPATBLE;
        $recouvreur = Roles::RECOUVREUR;
        $controlleur = Roles::CONTROLLEUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$agent|$controlleur|$comptable");
    }

    /**
     * Effectuer un destockage
     */
    // GESTIONNAIRE DE FLOTTE
    // RESPONSABLE DE ZONE
    // SUPERVISEUR
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'type' => ['required', 'string', 'max:255'], //BY_AGENT, BY_DIGIT_PARTNER or BY_BANK
            'id_fournisseur' => ['nullable', 'numeric'], // si le type est BY_DIGIT_PARTNER ou BY_BANK
            'id_agent' => ['nullable', 'numeric'],       // obligatoire si le type est BY_AGENT
            'id_puce' => ['required', 'numeric'],
            'montant' => ['required', 'numeric'],
            'cash_pay' => ['nullable', 'string'],
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

        $connected_user = Auth::user();
        $connected_caisse = $connected_user->caisse->first();
        $user_role = $connected_user->roles->first()->name;

        $is_supervisor = $user_role === Roles::SUPERVISEUR;
        $cash_pay = $request->cash_pay === Statut::EFFECTUER;

        // Vérification du solde caisse
        if (
            ($is_supervisor && $cash_pay && ($connected_caisse->solde < $montant)) ||
            (!$is_supervisor && ($connected_caisse->solde < $montant))
        ) {
            return response()->json([
                'message' => "Solde caisse insuffisant pour éffectuer cette opération",
                'status' => false,
                'data' => null
            ]);
        }

        $type = $request->type;
        $fournisseur = $request->id_fournisseur;
        $id_agent = $request->id_agent;
        $id_puce = $request->id_puce;

        $puce_receptrice = Puce::find($id_puce);
        $type_puce = $puce_receptrice->type_puce->name;

        $is_collector = $user_role === Roles::RECOUVREUR;

        $status = ($is_collector && ($type_puce !== Statut::PUCE_RZ)) ? Statut::EN_COURS : Statut::EFFECTUER;

        //initier le destockage encore appelé approvisionnement de ETP
        $destockage = new Destockage([
            'id_recouvreur' => $connected_user->id,
            'type' => $type,
            'id_puce' => $id_puce,
            'id_agent' => isset($id_agent) ? $id_agent : null,
            'id_fournisseur' => isset($fournisseur) ? $fournisseur : null,
            'statut' => $status,
            'montant' => $montant
        ]);
        $destockage->save();

        // Dimuner les especes dans la caisse si tous les profil sauf suerviseur avec cash activé
        if(($is_supervisor && $cash_pay) || !$is_supervisor) {
            $connected_caisse->solde = $connected_caisse->solde - $montant;
            $connected_caisse->save();
        }

        if ($status === Statut::EFFECTUER)
        {
            // Flotte ajouté dans les puce emetrice
            $puce_receptrice->solde = $puce_receptrice->solde + $montant;
            $puce_receptrice->save();

            // Imputer la dette au fournisseur si paiement par cash direct désactivé
            if(!$cash_pay && $is_supervisor) {
                $vendor = Vendor::find($fournisseur);
                $vendor->solde = $vendor->solde - $montant;
                $vendor->save();
            }

            if($type === Statut::BY_DIGIT_PARTNER || $type === Statut::BY_BANK) {
                // Garder la transaction éffectué par le SU
                Transaction::create([
                    'type' => Transations::APPROVISIONNEMENT,
                    'in' => $destockage->montant,
                    'out' => 0,
                    'id_operator' => $puce_receptrice->flote->id,
                    'id_left' => $puce_receptrice->id,
                    'right' => '(' . $destockage->fournisseur->name . ')',
                    'balance' => $puce_receptrice->solde,
                    'id_user' => $connected_user->id,
                ]);

                if($cash_pay) {
                    // Garder le mouvement de caisse éffectué par le RZ
                    Movement::create([
                        'name' => $destockage->fournisseur->name,
                        'type' => Transations::APPROVISIONNEMENT,
                        'in' => 0,
                        'out' => $destockage->montant,
                        'balance' => $connected_caisse->solde,
                        'id_user' => $connected_user->id,
                    ]);
                }
            }
            else if($type === Statut::BY_AGENT) {
                // Garder le mouvement de caisse éffectué par la GF ou RZ
                Movement::create([
                    'name' => $destockage->agent_user->name,
                    'type' => Transations::DESTOCKAGE,
                    'in' => 0,
                    'out' => $destockage->montant,
                    'balance' => $connected_caisse->solde,
                    'id_user' => $connected_user->id,
                ]);

                // Garder la transaction éffectué par la GF ou RZ
                Transaction::create([
                    'type' => Transations::DESTOCKAGE,
                    'in' => $destockage->montant,
                    'out' => 0,
                    'id_operator' => $puce_receptrice->flote->id,
                    'id_left' => $puce_receptrice->id,
                    'right' => '(' . $destockage->agent_user->name . ')',
                    'balance' => $puce_receptrice->solde,
                    'id_user' => $connected_user->id,
                ]);
            }
        }
        else if ($status === Statut::EN_COURS) {
            if($type === Statut::BY_AGENT) {
                $message = "Déstockage éffectué par " . $connected_user->name;
                //Database Notification
                $users = User::all();
                foreach ($users as $user) {
                    if ($user->hasRole([ Roles::GESTION_FLOTTE])) {
                        $user->notify(new Notif_destockage([
                            'data' => $destockage,
                            'message' => $message
                        ]));
                    }
                }

                //Database Notification de l'agent
                $destockage->agent_user->notify(new Notif_flottage([
                    'message' => $message,
                    'data' => $destockage
                ]));

                // Garder le mouvement de caisse éffectué par le RZ
                Movement::create([
                    'name' => $destockage->agent_user->name,
                    'type' => Transations::DESTOCKAGE,
                    'in' => 0,
                    'out' => $destockage->montant,
                    'balance' => $connected_caisse->solde,
                    'id_user' => $connected_user->id,
                ]);
            }
            else if($type === Statut::BY_DIGIT_PARTNER || $type === Statut::BY_BANK) {
                $message = "Approvisionnment éffectué par " . $connected_user->name;
                //Database Notification
                $users = User::all();
                foreach ($users as $user) {
                    if ($user->hasRole([Roles::SUPERVISEUR])) {
                        $user->notify(new Notif_destockage([
                            'data' => $destockage,
                            'message' => $message
                        ]));
                    }
                }

                // Garder le mouvement de caisse éffectué par le RZ
                Movement::create([
                    'name' => $destockage->fournisseur->name,
                    'type' => Transations::APPROVISIONNEMENT,
                    'in' => 0,
                    'out' => $destockage->montant,
                    'balance' => $connected_caisse->solde,
                    'id_user' => $connected_user->id,
                ]);
            }
        }

        $user = $destockage->id_agent === null ? $destockage->id_agent : $destockage->agent_user;
        $agent = $user === null ? $user : $user->agent->first();
        $message = ($type === Statut::BY_AGENT) ? "Déstockage effectué avec succès" : "Approvisionnement effectué avec succès";

        return response()->json([
            'message' => $message,
            'status' => true,
            'data' => [
                'id' => $destockage->id,
                'statut' => $status,
                'montant' => $montant,
                'created_at' => $destockage->created_at,
                'recouvreur' => $connected_user,
                'puce' => $puce_receptrice,
                'fournisseur' => $destockage->fournisseur,
                'agent' => $agent,
                'user' => $user,
                'operateur' => $puce_receptrice->flote
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
     * Approuver une demande de destockage
     */
    // GESTIONNAIRE DE FLOTTE
    public function approuve($id)
    {
        $destockage = Destockage::find($id);
        //si le destockage n'existe pas
        if (is_null($destockage)) {
            return response()->json([
                'message' => "Le destockage n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($destockage->statut === Statut::EFFECTUER) {
            return response()->json([
                'message' => "Le destockage a déjà été confirmé",
                'status' => false,
                'data' => null
            ]);
        }

        $connected_user = Auth::user();
        $montant = $destockage->montant;
        $puce_receptrice = $destockage->puce;
        $destokeur = $destockage->user;

        //on approuve le destockage
        $destockage->statut = Statut::EFFECTUER;

        // Flotte ajouté dans les puce emetrice
        $puce_receptrice->solde = $puce_receptrice->solde + $montant;
        $puce_receptrice->save();

        // Garder la transaction éffectué par la GF
        Transaction::create([
            'type' => Transations::DESTOCKAGE,
            'in' => $destockage->montant,
            'out' => 0,
            'id_operator' => $puce_receptrice->flote->id,
            'id_left' => $puce_receptrice->id,
            'right' => '(' . $destockage->agent_user->name . ')',
            'balance' => $puce_receptrice->solde,
            'id_user' => $connected_user->id,
        ]);

        // Baisser la dette du RZ
        $destokeur->dette = $destokeur->dette - $montant;
        $destokeur->save();

        $destockage->save();

        $message = "Déstockage apprové par " . $connected_user->name;
        //Database Notification du déstockeur RZ
        $destokeur->notify(new Notif_flottage([
            'message' => $message,
            'data' => $destockage
        ]));

        //Database Notification a
        $destockage->agent_user->notify(new Notif_flottage([
            'message' => $message,
            'data' => $destockage
        ]));

        return response()->json([
            'message' => "Déstockage apprové avec succès",
            'status' => true,
            'data' => null
        ]);
    }

    /**
     * Approuver un approvisionnement
     */
    // SUPERVISEUR
    public function approuve_approvisionnement($id)
    {
        //si le destockage n'existe pas
        $destockage = Destockage::find($id);
        if (is_null($destockage)) {
            return response()->json([
                'message' => "L'approvisionnement n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($destockage->statut === Statut::EFFECTUER) {
            return response()->json([
                'message' => "L'approvisionnement a déjà été confirmé",
                'status' => false,
                'data' => null
            ]);
        }

        $connected_user = Auth::user();
        $montant = $destockage->montant;
        $master_sim = $destockage->puce;
        $approvisionneur = $destockage->user;

        //on approuve le destockage
        $destockage->statut = Statut::EFFECTUER;

        // Augmenter la flotte dans la master sim
        $master_sim->solde = $master_sim->solde + $montant;
        $master_sim->save();

        // Garder la transaction éffectué par le SU
        Transaction::create([
            'type' => Transations::APPROVISIONNEMENT,
            'in' => $destockage->montant,
            'out' => 0,
            'id_operator' => $master_sim->flote->id,
            'id_left' => $master_sim->id,
            'right' => '(' . $destockage->fournisseur->name . ')',
            'balance' => $master_sim->solde,
            'id_user' => $connected_user->id,
        ]);

        // Baisser la dette du RZ
        $approvisionneur->dette = $approvisionneur->dette - $montant;
        $approvisionneur->save();

        $destockage->save();

        // Notifier si le RZ
        $message = "Approvisionnement apprové par " . $connected_user;
        $approvisionneur->notify(new Notif_destockage([
            'data' => $destockage,
            'message' => $message
        ]));

        return response()->json([
            'message' => "Approvisionnement apprové avec succès",
            'status' => true,
            'data' => null
        ]);
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
     * Lister les approvisionnements
     */
    // GESTIONNAIRE DE FLOTTE
    // SUPERVISEUR
    public function list_all()
    {
        $destockages = Destockage::where('type', Statut::BY_DIGIT_PARTNER)
            ->orWhere('type', Statut::BY_BANK)
            ->orderBy('created_at', 'desc')
            ->paginate(9);

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
     * Lister les approvisionnements par un responsable de zone
     */
    // RESPONSABLE DE ZONE
    public function list_all_collector()
    {
        $user = Auth::user();

        $destockages = Destockage::where('id_recouvreur', $user->id)
            ->where(function($query) {
                $query->where('type', Statut::BY_DIGIT_PARTNER);
                $query->orWhere('type', Statut::BY_BANK);
            })
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'destockages' => DestockageResource::collection($destockages->items()),
                'hasMoreData' => $destockages->hasMorePages(),
            ]
        ]);
    }

    // BY_AGENT
    /**
     * Lister les destockages
     */
    // GESTIONNAIRE DE FLOTTE
    public function list_all_destockage()
    {
        $destockages = Destockage::where('type', Statut::BY_AGENT)
            ->orderBy('created_at', 'desc')
            ->paginate(9);

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
     * Effectuer un destockage anonyme
     */
    // GESTIONNAIRE DE FLOTTE
    // RESPONSABLE DE ZONE
    public function destockage_anonyme(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'nom_agent' => ['nullable', 'string'],
            'id_puce_to' => ['required', 'numeric'],
            'montant' => ['required', 'numeric'],
            'nro_puce_from' => ['required', 'string'],
            'id_zone' => ['nullable', 'numeric'],
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

        $puce_receptrice = Puce::find($request->id_puce_to);
        //On verifi si la puce de  flottage passée existe réellement
        if (is_null($puce_receptrice)) {
            return response()->json([
                'message' => "La puce réceptrice n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $connected_user = Auth::user();
        $numero_agent = $request->nro_puce_from;

        // On verrifie si la puce anonyme existe dans la list des puces agents connus
        $agent_sim_type_id = Type_puce::where('name', Statut::AGENT)->get()->first()->id;
        $resource_sim_type_id = Type_puce::where('name', Statut::RESOURCE)->get()->first()->id;

        $needle_sim = Puce::where('numero', $numero_agent)->get()->first();

        if(
            ($needle_sim !== null) && (
                ($needle_sim->type == $agent_sim_type_id) ||
                ($needle_sim->type == $resource_sim_type_id)
            )
        ) {
            //======================================================================
            // Detection
            return response()->json([
                'message' => "Cette puce à été reconnu comme une puce agent/ressource. vous devez plutôt éffectuer un déstockage",
                'status' => false,
                'data' => null
            ]);
        } else {
            //======================================================================
            $needle_user = User::where('phone', $numero_agent)->get()->first();

            // Check de l'existence de la puce
            if($needle_sim !== null) {
                return response()->json([
                    'message' => "Ce compte existe déjà dans le système et ne peut être attribuée à un agent/ressource",
                    'status' => false,
                    'data' => null
                ]);
            }

            // Check l'existence de l'utilisateur
            if($needle_user !== null) {
                return response()->json([
                    'message' => "Ce numéro de téléphone est déjà utilisé par un utilisateur comme identifiant",
                    'status' => false,
                    'data' => null
                ]);
            }

            $zone = Zone::find($request->id_zone);
            //On verifie si la zone existe
            if (is_null($zone)) {
                return response()->json([
                    'message' => "La zone n'existe pas",
                    'status' => false,
                    'data' => null
                ]);
            }

            $nom_agent = $request->nom_agent;

            // Creation de l'utilisateur lié à l'agent
            $user = new User([
                'add_by' => $connected_user->id,
                'name' => $nom_agent,
                'password' => bcrypt(000000),
                'phone' => $numero_agent,
                'statut' => Statut::APPROUVE,
                'id_zone' => $zone->id
            ]);
            $user->save();

            // Creation de la caisse
            $caisse = new Caisse([
                'nom' => 'Caisse ' . $nom_agent,
                'id_user' => $user->id,
                'solde' => 0
            ]);
            $caisse->save();

            // Assigner le role à l'utilisateur
            $role = Role::where('name', Roles::AGENT)->first();
            $user->assignRole($role);

            // Création des paramettres de l'utilisateur
            $user->setting()->create([
                'bars' => '[0,1,2,3,4,5,6,7,8,9]',
                'charts' => '[0,1,2,3,4,5,6,7,8,9]',
                'cards' => '[0,1,2,3,4,5,6,7,8,9]',
            ]);

            // Creation de agent
            $agent = new Agent([
                'id_creator' => $connected_user->id,
                'id_user' => $user->id,
                'reference' => Roles::AGENT,
                'ville' => 'Douala',
                'pays' => 'CAMEROUN'
            ]);
            $agent->save();

            // Création de la puce agent
            $puce = new Puce([
                'nom' => $nom_agent,
                'type' => $agent_sim_type_id,
                'numero' => $numero_agent,
                'id_agent' => $agent->id,
                'reference' => Roles::AGENT,
                'id_flotte' => $puce_receptrice->flote->id,
                'solde' => 0
            ]);
            $puce->save();

            $type = Statut::BY_AGENT;
            $type_puce = $puce_receptrice->type_puce->name;

            $user_role = $connected_user->roles->first()->name;
            $is_collector = $user_role === Roles::RECOUVREUR;

            $status = ($is_collector && ($type_puce !== Statut::PUCE_RZ)) ? Statut::EN_COURS : Statut::EFFECTUER;

            //initier le destockage encore appelé approvisionnement de ETP
            $destockage = new Destockage([
                'id_recouvreur' => $connected_user->id,
                'type' => $type,
                'id_puce' => $puce_receptrice->id,
                'id_agent' => $user->id,
                'statut' => $status,
                'montant' => $montant
            ]);
            $destockage->save();

            // Dimuner les especes dans la caisse
            $connected_caisse = $connected_user->caisse->first();
            $connected_caisse->solde = $connected_caisse->solde - $montant;
            $connected_caisse->save();

            // Garder le mouvement de caisse éffectué par le RZ
            Movement::create([
                'name' => $destockage->agent_user->name,
                'type' => Transations::DESTOCKAGE,
                'in' => 0,
                'out' => $destockage->montant,
                'balance' => $connected_caisse->solde,
                'id_user' => $connected_user->id,
            ]);

            //Notification
            if($status === Statut::EN_COURS)
            {
                $message = "Déstockage éffectué par " . $connected_user->name;
                //Database Notification
                $users = User::all();
                foreach ($users as $user) {
                    if ($user->hasRole([Roles::GESTION_FLOTTE])) {
                        $user->notify(new Notif_destockage([
                            'data' => $destockage,
                            'message' => $message
                        ]));
                    }
                }

                //Database Notification de l'agent
                $user->notify(new Notif_flottage([
                    'message' => $message,
                    'data' => $destockage
                ]));
            }
            else if($status === Statut::EFFECTUER)
            {
                // Flotte ajouté dans les puce emetrice
                $puce_receptrice->solde = $puce_receptrice->solde + $montant;
                $puce_receptrice->save();

                // Garder la transaction éffectué par la GF
                Transaction::create([
                    'type' => Transations::DESTOCKAGE,
                    'in' => $destockage->montant,
                    'out' => 0,
                    'id_operator' => $puce_receptrice->flote->id,
                    'id_left' => $puce_receptrice->id,
                    'right' => '(' . $destockage->agent_user->name . ')',
                    'balance' => $puce_receptrice->solde,
                    'id_user' => $connected_user->id,
                ]);
            }

            return response()->json([
                'message' => "Déstockage effectué avec succès",
                'status' => true,
                'data' => [
                    'id' => $destockage->id,
                    'statut' => $status,
                    'montant' => $montant,
                    'created_at' => $destockage->created_at,
                    'recouvreur' => $connected_user,
                    'puce' => $puce_receptrice,
                    'agent' => $agent,
                    'user' => $user,
                    'operateur' => $puce_receptrice->flote
                ]
            ]);
        }
    }

    /**
     * Lister les destockages par un responsable de zone
     */
    // RESPONSABLE DE ZONE
    public function list_all_destockage_collector()
    {
        $user = Auth::user();
        $destockages = Destockage::where('type', Statut::BY_AGENT)
            ->where('id_recouvreur', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(9);

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
     * ////lister les destockages par un agent
     */
    public function list_all_destockage_agent()
    {
        $user = Auth::user();
        $destockages = Destockage::where('type', Statut::BY_AGENT)
            ->where('id_agent', $user->id)
            ->orderBy('created_at', 'desc')
            ->paginate(9);

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'destockages' => DestockageResource::collection($destockages->items()),
                'hasMoreData' => $destockages->hasMorePages(),
            ]
        ]);
    }
}

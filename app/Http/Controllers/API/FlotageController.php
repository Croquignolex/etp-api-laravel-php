<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Puce;
use App\Zone;
use App\Role;
use App\Agent;
use App\Caisse;
use App\Movement;
use App\Type_puce;
use App\Transaction;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Recouvrement;
use App\Demande_flote;
use App\FlotageAnonyme;
use App\Enums\Transations;
use App\Approvisionnement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Events\NotificationsEvent;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Flottage as Notif_flottage;
use App\Notifications\Recouvrement as Notif_recouvrement;

class FlotageController extends Controller
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
     * @param Request $request
     * @return JsonResponse
     */
    // GESTIONNAIRE DE FLOTTE
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_demande_flotte' => ['required', 'numeric'],
            'id_puce' => ['required', 'numeric']
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

        $demande_flotte = Demande_flote::find($request->id_demande_flotte);

        //On verifi si la demande passée existe réellement
        if (is_null($demande_flotte)) {
            return response()->json([
                'message' => "La demande de flotte n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($demande_flotte->statut === Statut::ANNULE) {
            return response()->json([
                'message' => "La demande de flotte a déjà été annulée",
                'status' => false,
                'data' => null
            ]);
        }

        // Vérification de la validation éffective
        if ($demande_flotte->statut === Statut::EFFECTUER) {
            return response()->json([
                'message' => "La demande de flotte a déjà été confirmée",
                'status' => false,
                'data' => null
            ]);
        }

        //On verifi que le montant n'est pas supperieur au montant demandé
        if ($demande_flotte->reste < $montant) {
            return response()->json([
                'message' => "Vous essayez d'envoyer plus de flotte que prevu",
                'status' => false,
                'data' => null
            ]);
        }

        // Data
        $puce_etp = Puce::find($request->id_puce);
        $puce_agent = Puce::find($demande_flotte->id_puce);
        $type_puce = Type_puce::find($puce_etp->type)->name;

        // Existance des puces
        if(is_null($puce_etp) || is_null($puce_agent)) {
            return response()->json([
                'message' => "Une des puces n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        // capacité de flottage de la puce emetrice
        if($type_puce !== Statut::FLOTTAGE) {
            return response()->json([
                'message' => "Cette puce n'est pas capable d'effectuer un flottage",
                'status' => false,
                'data' => null
            ]);
        }

        // Verification d'opérateur
        if ($puce_etp->id_flotte != $puce_agent->id_flotte) {
            return response()->json([
                'message' => "Les deux puces ne sont pas du même opérateur",
                'status' => false,
                'data' => null
            ]);
        }

        // Solde flotte insufisant
        if($puce_etp->solde < $montant) {
            return response()->json([
                'message' => "Solde flotte insufisant dans cette puce de flottage",
                'status' => false,
                'data' => null
            ]);
        }

        //La gestionnaire concernée
        $connected_user = Auth::user();

        // Nouveau flottage
        $flottage = new Approvisionnement([
            'reste' => $montant,
            'montant' => $montant,
            'from' => $puce_etp->id,
            'statut' => Statut::EN_ATTENTE,
            'id_user' => $connected_user->id,
            'id_demande_flote' => $demande_flotte->id,
        ]);
        $flottage->save();

        //Broadcast Notification des responsables de zone
        $message = "Flottage éffectué par " . $connected_user->nom;
        $role = Role::where('name', Roles::RECOUVREUR)->first();
        $event = new NotificationsEvent($role->id, ['message' => $message]);
        broadcast($event)->toOthers();

        //Database Notification
        $users = User::all();
        foreach ($users as $_user)
        {
            if ($_user->hasRole([$role->name]))
            {
                $_user->notify(new Notif_flottage([
                    'data' => $flottage,
                    'message' => $message
                ]));
            }
        }

        //Database Notification de l'agent
        $demande_flotte->user->notify(new Notif_flottage([
            'message' => $message,
            'data' => $flottage
        ]));

        ////ce que le flottage implique

        //On debite la puce de ETP
        $puce_etp->solde = $puce_etp->solde - $montant;
        $puce_etp->save();

        //On credite la puce de l'Agent
        $puce_agent->solde = $puce_agent->solde + $montant;
        $puce_agent->save();

        // Garder la transaction éffectué par la GF
        Transaction::create([
            'type' => Transations::FLOTAGE,
            'in' => 0,
            'out' => $flottage->montant,
            'id_operator' => $puce_etp->flote->id,
            'id_left' => $puce_etp->id,
            'id_right' => $puce_agent->id,
            'balance' => $puce_etp->solde,
            'id_user' => $connected_user->id,
        ]);

        //On calcule le reste de flotte à envoyer
        $demande_flotte->reste = $demande_flotte->montant - $montant;
        $demande_flotte->statut = Statut::EFFECTUER ;
        $demande_flotte->source = $puce_etp->id;
        $demande_flotte->save();

        // Renvoyer un message de succès
        return response()->json([
            'message' => "Flottage effectué avec succès",
            'status' => true,
            'data' => null
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    // GESTIONNAIRE DE FLOTTE
    public function store_groupe(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'ids_demande_flotte' => ['required'],
            'id_puce' => ['required', 'numeric']
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // Data
        $montant = $request->montant;
        $puce_etp = Puce::find($request->id_puce);
        $type_puce = Type_puce::find($puce_etp->type)->name;

        // capacité de flottage de la puce emetrice
        if($type_puce !== Statut::FLOTTAGE) {
            return response()->json([
                'message' => "Cette puce n'est pas capable d'effectuer un flottage",
                'status' => false,
                'data' => null
            ]);
        }

        // Solde flotte insufisant
        if($puce_etp->solde < $montant) {
            return response()->json([
                'message' => "Solde flotte insufisant dans cette puce de flottage",
                'status' => false,
                'data' => null
            ]);
        }

        //La gestionnaire concernée
        $connected_user = Auth::user();

        foreach ($request->ids_demande_flotte as $id){
            // Data
            $demande_flotte = Demande_flote::find($id);

            $montant_flottage = $montant;

            if($montant != 0) {
                if ($montant > $demande_flotte->montant) {
                    $montant_flottage = $demande_flotte->montant;
                }

                $montant = $montant - $montant_flottage;

                if(
                    !is_null($demande_flotte) &&
                    $demande_flotte->statut !== Statut::ANNULE &&
                    $demande_flotte->statut !== Statut::EFFECTUER
                ) {
                    $puce_agent = Puce::find($demande_flotte->id_puce);

                    // Nouveau flottage
                    $flottage = new Approvisionnement([
                        'reste' => $montant_flottage,
                        'montant' => $montant_flottage,
                        'from' => $puce_etp->id,
                        'statut' => Statut::EN_ATTENTE,
                        'id_user' => $connected_user->id,
                        'id_demande_flote' => $demande_flotte->id,
                    ]);
                    $flottage->save();

                    //On credite la puce de l'Agent
                    $puce_agent->solde = $puce_agent->solde + $montant_flottage;
                    $puce_agent->save();

                    //On debite la puce de ETP
                    $puce_etp->solde = $puce_etp->solde - $montant_flottage;
                    $puce_etp->save();

                    // Garder la transaction éffectué par la GF
                    Transaction::create([
                        'type' => Transations::FLOTAGE,
                        'in' => 0,
                        'out' => $flottage->montant,
                        'id_operator' => $puce_etp->flote->id,
                        'id_left' => $puce_etp->id,
                        'id_right' => $puce_agent->id,
                        'balance' => $puce_etp->solde,
                        'id_user' => $connected_user->id,
                    ]);

                    //On calcule le reste de flotte à envoyer
                    $demande_flotte->reste = $demande_flotte->montant - $montant_flottage;
                    $demande_flotte->statut = Statut::EFFECTUER ;
                    $demande_flotte->source = $puce_etp->id;
                    $demande_flotte->save();
                }
            }
        }

        // Renvoyer un message de succès
        return response()->json([
            'message' => "Flottage groupée effectué avec succès",
            'status' => true,
            'data' => null
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    // GESTIONNAIRE DE FOTTE
    // RESPONSABLE DE ZONE
    // SUPERVISEUR
    public function flottage_express(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_agent' => ['required', 'numeric'],
            'id_puce_agent' => ['required', 'numeric'],
            'id_puce_flottage' => ['required', 'numeric'],
            'direct_pay' => ['nullable', 'string'],
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

        $user = User::find($request->id_agent);
        //On verifi si l'agent passé existe réellement
        if (is_null($user)) {
            return response()->json([
                'message' => "Cet agent n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $puce_agent = Puce::find($request->id_puce_agent);
        //On verifi si la puce agent passée existe réellement
        if (is_null($puce_agent)) {
            return response()->json([
                'message' => "La puce receptrice n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $puce_etp = Puce::find($request->id_puce_flottage);
        //On verifi si la puce de  flottage passée existe réellement
        if (is_null($puce_etp)) {
            return response()->json([
                'message' => "La puce émetrice n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $connected_user = Auth::user();
        // Verification du solde
        if($puce_etp->solde < $montant) {
            return response()->json([
                'message' => "Solde flotte insuffisant dans la puce émétrice pour cette opération",
                'status' => false,
                'data' => null
            ]);
        }

        //On se rassure que la puce passée en paramettre est reelement l'une des puces de flottage sollicités
        if ($puce_etp->id_flotte != $puce_agent->id_flotte) {
            return response()->json([
                'message' => "Les deux puces ne sont pas du même opérateur",
                'status' => false,
                'data' => null
            ]);
        }

        // Nouvelle demande fictive de flotte
        $demande_flotte = new Demande_flote([
            'id_user' => $user->id,
            'add_by' => $connected_user->id,
            'montant' => $montant,
            'reste' => 0,
            'statut' => Statut::EFFECTUER,
            'id_puce' => $puce_agent->id,
            'source' => $puce_etp->id
        ]);
        $demande_flotte->save();

        // Nouveau flottage
        $flottage = new Approvisionnement([
            'id_demande_flote' => $demande_flotte->id,
            'id_user' => $connected_user->id,
            'statut' => Statut::EN_ATTENTE,
            'from' => $puce_etp->id,
            'montant' => $montant,
            'reste' => $montant
        ]);
        $flottage->save();

        $is_collector = $connected_user->roles->first()->name === Roles::RECOUVREUR;
        // Appliquer le paiement direct
        if($request->direct_pay === Statut::EFFECTUER)
        {
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

            $caisse = $user->caisse->first();
            //On credite la caisse de l'Agent pour le remboursement de la flotte recu, ce qui implique qu'il rembource ses detes à ETP
            $caisse->solde = $caisse->solde - $montant;
            $caisse->save();

            //la caisse de l'utilisateur connecté
            $connected_caisse = $connected_user->caisse->first();
            // Augmenter la caisse
            $connected_caisse->solde = $connected_caisse->solde + $montant;
            $connected_caisse->save();

            $daily_report_status = !$is_collector;

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

            //On calcule le reste à recouvrir
            $flottage->reste = 0;
            $flottage->statut = Statut::EFFECTUER;
            $flottage->save();
        }

        if($is_collector) {
            // paiement direct du flottage par le RZ
            if($request->direct_pay !== Statut::EFFECTUER)
            {
                // Augmenter la caisse du RZ et augmenter sa dette
                $connected_user->dette = $connected_user->dette - $montant;
                $connected_user->save();
            }
        }
        else
        {
            $message = "Flottage éffectué par " . $connected_user->name;
            //noifier les responsables de zonne
            $users = User::all();
            foreach ($users as $_user) {
                if ($_user->hasRole([Roles::RECOUVREUR])) {
                    $_user->notify(new Notif_flottage([
                        'data' => $flottage,
                        'message' => $message
                    ]));
                }
            }
        }

        $message = "Flottage éffectué par " . $connected_user->name;
        //noifier l'agent concerné
        $user->notify(new Notif_flottage([
            'data' => $flottage,
            'message' => $message
        ]));

        ////ce que le flottage implique

        //On debite la puce de ETP
        $puce_etp->solde = $puce_etp->solde - $montant;
        $puce_etp->save();

        //On credite la puce de l'Agent
        $puce_agent->solde = $puce_agent->solde + $montant;
        $puce_agent->save();

        // Garder la transaction éffectué par la GF
        Transaction::create([
            'type' => Transations::FLOTAGE,
            'in' => 0,
            'out' => $flottage->montant,
            'id_operator' => $puce_etp->flote->id,
            'id_left' => $puce_etp->id,
            'id_right' => $puce_agent->id,
            'balance' => $puce_etp->solde,
            'id_user' => $connected_user->id,
        ]);

        return response()->json([
            'message' => 'Flottage effectué avec succès',
            'status' => true,
            'data' => [
                'approvisionnement' => $flottage,
                'user' => $user,
                'agent' => $user->agent->first(),
                'gestionnaire' => $connected_user,
                'puce_emetrice' => $puce_etp,
                'puce_receptrice' => $puce_agent,
                'operateur' => $puce_etp->flote,
            ]
        ]);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
    // GESTIONNAIRE DE FOTTE
    // RESPONSABLE DE ZONE
    // SUPERVISEUR
    public function flottage_anonyme(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'nom_agent' => ['required', 'string'], //le nom de celui qui recoit la flotte
            'id_puce_from' => ['required', 'numeric'],
            'direct_pay' => ['nullable', 'string'],
            'nro_puce_to' => ['required', 'string'], //le numéro de la puce qui recoit la flotte
            'id_zone' => ['required', 'numeric'],
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

        $zone = Zone::find($request->id_zone);
        //On verifie si la zone existe
        if (is_null($zone)) {
            return response()->json([
                'message' => "La zone n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        $puce_from = Puce::find($request->id_puce_from);
        //On verifi si la puce de  flottage passée existe réellement
        if (is_null($puce_from)) {
            return response()->json([
                'message' => "La puce émetrice n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        //On se rassure que le solde est suffisant
        if ($puce_from->solde < $montant) {
            return response()->json([
                'message' => "Solde insuffisant dans la puce émetrice",
                'status' => false,
                'data' => null
            ]);
        }

        //L'utilisateur qui envoie
        $connected_user = Auth::user();
        $numero_agent = $request->nro_puce_to;

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
                'message' => "Cette puce à été reconnu comme une puce agent/ressource. vous devez plutôt éffectuer un flottage",
                'status' => false,
                'data' => null
            ]);
        } else {
            //======================================================================
            $needle_user = User::where('phone', $numero_agent)->get()->first();
            $is_collector = $connected_user->roles->first()->name === Roles::RECOUVREUR;

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

            $nom_agent = $request->nom_agent;

            // Creation de l'utilisateur lié à l'agent
            $user = new User([
                'add_by' => $connected_user->id,
                'name' => $nom_agent,
                'password' => bcrypt("000000"),
                'phone' => $numero_agent,
                'statut' => Statut::APPROUVE,
                'id_zone' => $zone->id,
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
                'id_flotte' => $puce_from->flote->id,
                'solde' => 0
            ]);
            $puce->save();

            // Récupérer les données pour la création d'une demande fictive de flotte
            $id_user = $user->id;
            $add_by = $user->id;
            $statut = Statut::EFFECTUER;
            $source = $puce_from->id;
            $id_puce = $puce->id;

            // Nouvelle demande fictive de flotte
            $demande_flotte = new Demande_flote([
                'id_user' => $id_user,
                'add_by' => $add_by,
                'montant' => $montant,
                'reste' => 0,
                'statut' => $statut,
                'id_puce' => $id_puce,
                'source' => $source
            ]);
            $demande_flotte->save();

            // Nouveau flottage
            $flottage = new Approvisionnement([
                'id_demande_flote' => $demande_flotte->id,
                'id_user' => $connected_user->id,
                'statut' => Statut::EN_ATTENTE,
                'from' => $puce_from->id,
                'montant' => $montant,
                'reste' => $montant
            ]);
            $flottage->save();

            // Paiement direct du flottage par le RZ
            if($request->direct_pay === Statut::EFFECTUER) {
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

                $caisse = $user->caisse->first();
                //On credite la caisse de l'Agent pour le remboursement de la flotte recu, ce qui implique qu'il rembource ses detes à ETP
                $caisse->solde = $caisse->solde - $montant;
                $caisse->save();

                //la caisse de l'utilisateur connecté
                $connected_caisse = $connected_user->caisse->first();
                // Augmenter la caisse
                $connected_caisse->solde = $connected_caisse->solde + $montant;
                $connected_caisse->save();

                $daily_report_status = !$is_collector;

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

                //On calcule le reste à recouvrir
                $flottage->reste = 0;
                $flottage->statut = Statut::EFFECTUER;
                $flottage->save();
            }

            if($is_collector) {
                // paiement direct du flottage par le RZ
                if($request->direct_pay !== Statut::EFFECTUER)
                {
                    // Augmenter la caisse du RZ et augmenter sa dette
                    $connected_user->dette = $connected_user->dette - $montant;
                    $connected_user->save();
                }
            }
            else
            {
                $message = "Flottage éffectué par " . $connected_user->name;
                //noifier les responsables de zonne
                $users = User::all();
                foreach ($users as $_user) {
                    if ($_user->hasRole([Roles::RECOUVREUR])) {
                        $_user->notify(new Notif_flottage([
                            'data' => $flottage,
                            'message' => $message
                        ]));
                    }
                }
            }

            $message = "Flottage éffectué par " . $connected_user->name;
            //noifier l'agent concerné
            $user->notify(new Notif_flottage([
                'data' => $flottage,
                'message' => $message
            ]));

            ////ce que le flottage implique

            //On credite la puce de l'Agent
            $puce->solde = $puce->solde + $montant;
            $puce->save();

            $puce_from->solde = $puce_from->solde - $montant;
            $puce_from->save();

            // Garder la transaction éffectué par la GF
            Transaction::create([
                'type' => Transations::FLOTAGE,
                'in' => 0,
                'out' => $flottage->montant,
                'id_operator' => $puce_from->flote->id,
                'id_left' => $puce_from->id,
                'id_right' => $puce->id,
                'balance' => $puce_from->solde,
                'id_user' => $connected_user->id,
            ]);

            return response()->json([
                'message' => 'Flottage éffectué avec succès. Nouvel agent détecté et enreistré avec succès',
                'status' => true,
                'data' => [
                    'approvisionnement' => $flottage,
                    'user' => $user,
                    'agent' => $agent,
                    'gestionnaire' => $connected_user,
                    'puce_emetrice' => $puce_from,
                    'puce_receptrice' => $puce,
                    'operateur' => $puce->flote,
                ]
            ]);
        }
    }

    /**
     * Annuler le flottage
     */
    // SUPERVISEUR
    // RESPONSABLE DE ZONE
    // GESTIONNAIRE DE FLOTTE
    public function annuler_flottage($id)
    {
        //si le destockage n'existe pas
        $flottage = Approvisionnement::find($id);
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

        $montant = $flottage->montant;
        $puce_flottage = $flottage->puce;
        $puce_agent = $flottage->demande_flote->puce;

        //on approuve le flottage
        $flottage->statut = Statut::ANNULE;

        // Traitement des flottes
        $puce_flottage->solde = $puce_flottage->solde + $montant;
        $puce_flottage->save();

        $puce_agent->solde = $puce_agent->solde - $montant;
        $puce_agent->save();

        $connected_user = Auth::user();
        $is_collector = $connected_user->roles->first()->name === Roles::RECOUVREUR;
        if($is_collector) {
            $connected_user->dette = $connected_user->dette + $montant;
            $connected_user->save();
        }

        // Garder la transaction éffectué par la GF
        Transaction::create([
            'type' => Transations::FLOTAGE,
            'in' => $flottage->montant,
            'out' => 0,
            'id_operator' => $puce_flottage->flote->id,
            'id_left' => $puce_flottage->id,
            'right' => "(Annulation)",
            'balance' => $puce_flottage->solde,
            'id_user' => $connected_user->id,
        ]);

        $flottage->save();

        return response()->json([
            'message' => "Flottage annulé avec succès",
            'status' => true,
            'data' => null
        ]);
    }

    /**
     * Lister tous les flottages
     */
    // SUPERVISEUR
    // RESPONSABLE DE ZONE
    // GESTIONNAIRE DE FLOTTE
    public function list_all()
    {
        $demandes_flote = Approvisionnement::orderBy('created_at', 'desc')->paginate(9);

        $demandes_flotes =  $this->fleetsResponse($demandes_flote->items());

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'flottages' => $demandes_flotes,
                'hasMoreData' => $demandes_flote->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister tous les flottages groupee
     */
    // SUPERVISEUR
    // RESPONSABLE DE ZONE
    // GESTIONNAIRE DE FLOTTE
    public function list_all_groupee()
    {
        $demandes_flote = Approvisionnement::where(function($query) {
                $query->where('statut', Statut::EN_COURS);
                $query->orWhere('statut', Statut::EN_ATTENTE);
            })
            ->orderBy('created_at', 'desc')
            ->get();

        $demandes_flotes = $this->fleetsResponse($demandes_flote);

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'flottages' => $demandes_flotes,
            ]
        ]);
    }

    /**
     * Lister tous les flottages par chaine de recherche
     */
    // RESPONSABLE DE ZONE
    // GESTIONNAIRE DE FLOTTE
    // SUPERVISEUR
    public function list_search(Request $request)
    {
        $needle = mb_strtolower($request->query('needle'));

        $demandes_flotes = Approvisionnement::orderBy('created_at', 'desc')->get()->filter(function (Approvisionnement $approvisionnement) use ($needle) {

            $user = mb_strtolower($approvisionnement->demande_flote->user->name);
            $gestionnaire = mb_strtolower($approvisionnement->user->name);
            $puce_receptrice = $approvisionnement->demande_flote->puce->numero;
            $puce_emetrice = $approvisionnement->puce->numero;
            $operateur = mb_strtolower($approvisionnement->puce->flote->nom);
            $montant = $approvisionnement->montant;

            return (
                strstr($user, $needle) ||
                strstr($gestionnaire, $needle) ||
                strstr($puce_receptrice, $needle) ||
                strstr($puce_emetrice, $needle) ||
                strstr($operateur, $needle) ||
                strstr($montant, $needle)
            );
        });

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'flottages' =>  $this->fleetsResponse($demandes_flotes)
            ]
        ]);
    }

    /**
     * ////lister tous les flottages pour un agent
     */
    public function list_all_agent()
    {
        $user = Auth::user();

        //On recupere les Flottages
        $flottages = Approvisionnement::orderBy('created_at', 'desc')
            ->get()
            ->filter(function(Approvisionnement $approvisionnement) use ($user) {
                $demande_de_flotte = $approvisionnement->demande_flote;
                return ($demande_de_flotte->user->id == $user->id);
            });

        $demandes_flotes =  $this->fleetsResponse($flottages);

        return response()->json([
            'message' => "",
            'status' => true,
            'data' => [
                'flottages' => $demandes_flotes,
                'hasMoreData' => false,
            ]
        ]);
    }

    /**
     * ////lister tous les flottages pour responsable de zone
     */
    public function list_all_collector($id)
    {
        //On recupere les Flottages
        $flottages = Approvisionnement::get()->filter(function(Approvisionnement $approvisionnement) use ($id) {
            $demande_de_flotte = $approvisionnement->demande_flote;
            return ($demande_de_flotte->add_by == $id);
        });

        $approvisionnements = [];

        foreach($flottages as $flottage) {

            //recuperer la demande correspondante
            $demande = $flottage->demande_flote;

            //recuperer l'agent concerné
            $user = $demande->user;

            //recuperer l'agent concerné
            $agent = Agent::where('id_user', $user->id)->first();

            // recuperer celui qui a effectué le flottage
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
                'data' => ['flottages' => $approvisionnements]
            ]
        );
    }

    /**
     * ////details d'un flottages
     */
    public function show($id_flottage)
    {
        //On recupere le Flottage
        $flottage = Approvisionnement::find($id_flottage);

        //recuperer la demande correspondante
        $demande = $flottage->demande_flote()->first();

        //recuperer celui qui a effectué le flottage
            $user = User::Find($flottage->id_user);

        //recuperer l'agent concerné
            $agent = Agent::where('id_user', $demande->id_user)->get();

        //recuperer la puce de l'agent
            $puce_receptrice = Puce::Find($demande->id_puce);

        $approvisionnements[] = ['approvisionnement' => $flottage,'demande' => $demande, 'user' => $user, 'agent' => $agent, 'puce_receptrice' => $puce_receptrice,];



        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $approvisionnements]
            ]
        );

    }

    /**
     * ////lister les flottages d'une demande
     */
    public function list_flottage($id)
    {
        if (!Demande_flote::Find($id)){

            return response()->json(
                [
                    'message' => "la demande specifiée n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }

        //On recupere les Flottage
        $flottages = Approvisionnement::where('id_demande_flote', $id)->get();


        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $flottages]
            ]
        );

    }

    /**
     * ////détails d'un flottage effectué pour un anonyme
     */
    public function show_flottage_anonyme($id)
    {

        //On recupere la Flottages
        $flottage = FlotageAnonyme::find($id);


        if (!is_null($flottage)){
            //puce de l'envoie
            $puce_envoie = Puce::find($flottage->id_sim_from);
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottage' => $flottage, 'puce_envoie' => $puce_envoie  ]
            ]
        );
    }

    /**
     * ////lister les flottages anonyme effectués par un user precis
     */
    public function flottage_anonyme_by_user($id)
    {
        //On recupere les Flottages anonymes d'un utilisateur
        $flottages_anonymes = FlotageAnonyme::All();

        $flottages = [];

        foreach($flottages_anonymes as $flottage) {

            //puce de l'envoie
            $puce_envoie = Puce::find($flottage->id_sim_from);

            if(($flottage->id_user == $id)) {
                // Take only the current user sending sims
                $flottages[] = [
                    'puce_emetrice' => $puce_envoie,
                    'user' => User::find($id),
                    'flottage' => $flottage
                ];
            }

        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $flottages ]
            ]
        );
    }

    /**
     * ////lister les flottages anonyme effectués par un user precis
     */
    public function list_flottage_anonyme()
    {
        $anonymous = null;

        $connected_user = Auth::user();
        if($connected_user->roles->first()->name === Roles::SUPERVISEUR) {
            $anonymous = FlotageAnonyme::orderBy('created_at', 'desc')
                ->paginate(6);
        } else {
            $anonymous = FlotageAnonyme::where('id_user', $connected_user->id)
                ->where('reference', Statut::FLOTTAGE_ANONYME_GESTIONNAIRE)
                ->orderBy('created_at', 'desc')->paginate(6);
        }

        $anonymous_response =  $this->anonymousResponse($anonymous->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'flottages' => $anonymous_response,
                'hasMoreData' => $anonymous->hasMorePages(),
            ]
        ]);
    }

    // Build anonymous return data
    private function anonymousResponse($anonymous)
    {
        $returnedAnonymous = [];

        foreach($anonymous as $anonyme)
        {
            //puce de l'envoie
            $puce_envoie = Puce::find($anonyme->id_sim_from);

            $returnedAnonymous[] = [
                'puce_emetrice' => $puce_envoie,
                'user' => User::find($anonyme->id_user),
                'flottage' => $anonyme
            ];
        }

        return $returnedAnonymous;
    }

    // Build fleets return data
    private function fleetsResponse($fleets)
    {
        $approvisionnements = [];

        foreach($fleets as $flottage)
        {
            $demande = $flottage->demande_flote;
            $user = $demande->user;
            $agent = $user->agent->first();
            $gestionnaire = $flottage->user;
            $puce_receptrice = $demande->puce;
            $puce_emetrice = $flottage->puce;

            $approvisionnements[] = [
                'approvisionnement' => $flottage,
                'user' => $user,
                'agent' => $agent,
                'gestionnaire' => $gestionnaire,
                'puce_emetrice' => $puce_emetrice,
                'puce_receptrice' => $puce_receptrice,
                'operateur' => $puce_emetrice->flote,
            ];
        }

        return $approvisionnements;
    }
}

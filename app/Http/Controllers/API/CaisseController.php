<?php

namespace App\Http\Controllers\API;

use App\Enums\Statut;
use App\Events\NotificationsEvent;
use App\Notifications\Recouvrement;
use App\Operation;
use App\Role;
use App\User;
use App\Caisse;
use App\Versement;
use App\Enums\Roles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Recouvrement as Notif_recouvrement;

class CaisseController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct(){

        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$ges_flotte");
    }

    /**
     * //Creer un Encaissement
     */
    public function encassement(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_donneur' => ['required', 'Numeric'], //id de l'utilisateur qui verse l'argent
            'montant' => ['required', 'Numeric'],
            'recu' => ['required', 'file', 'max:10000']
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

        //On verifi si le donneur est vraiment utilisateur
        if (!($donneur = User::find($request->id_donneur))) {
            return response()->json(
                [
                    'message' => "l'utilisateur en paramettre n'existe pas en BD",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //recuperer la caisse de l'utilisateur qui effecttue le verssement
        $caisse_donneur = $donneur->caisse->first();

        //recuperer la caisse de l'utilisateur connecté
        $user = Auth::user();
        $caisse = $user->caisse->first();

        // Récupérer les données validées
        $recu = null;
        if ($request->hasFile('recu') && $request->file('recu')->isValid()) {
            $recu = $request->recu->store('files/recu/versement');
        }

        $id_donneur = $request->id_donneur;
        $montant = $request->montant;
        $id_caisse = $caisse->id;
        $add_by = $user->id;
        $note = "pour un versement";

        // Nouveau versement
        $versement = new Versement ([
            'recu' => $recu,
            'correspondant' => $id_donneur,
            'montant' => $montant,
            'id_caisse' => $id_caisse,
            'add_by' => $add_by,
            'note' => $note
        ]);

        // creation du versement
        if ($versement->save()) {

            //notification du donneur
            $donneur = User::find($request->id_donneur);
            $donneur->notify(new Notif_recouvrement([
                'data' => $versement,
                'message' => "Nouveau versement de votre part"
            ]));

            //on credite le compte du donneur
            $caisse_donneur->solde = $caisse_donneur->solde + $montant;
            $caisse_donneur->save();

            //on credite le compte de la gestionnaire de flotte
            $caisse->solde = $caisse->solde + $montant;
            $caisse->save();

            $versements = Versement::All();
            $encaissements = [];
            foreach ($versements as $_versement) {
                $id_caisse_gestionnaire = Caisse::where('id_user', $_versement->add_by)->first();
                if ($id_caisse_gestionnaire->id == $_versement->id_caisse) {
                    $encaissements[] = [
                        'versement' => $_versement,
                        'gestionnaire' => User::find($_versement->add_by),
                        'recouvreur' => User::find($_versement->correspondant),
                    ];
                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'versements' => $encaissements
                    ]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la Creation',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * //Creer un Decaissement
     */
    public function decaissement(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_receveur' => ['required', 'Numeric'], //id de l'utilisateur qui recoit l'argent
            'montant' => ['required', 'Numeric'],
            'recu' => ['required', 'file', 'max:10000']
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

        //On verifi si le receveur est vraiment utilisateur
        if (!($receveur = User::find($request->id_receveur))) {
            return response()->json(
                [
                    'message' => "l'utilisateur en paramettre n'existe pas en BD",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //recuperer la caisse de l'utilisateur qui recoit le verssement
        $caisse_receveur = $receveur->caisse->first();

        //recuperer la caisse de l'utilisateur connecté
        $user = Auth::user();
        $caisse = $user->caisse->first();

        //On verifi si le compte du payeur est suffisant
        if ($caisse->solde < $request->montant) {
            return response()->json(
                [
                    'message' => "le solde du payeur est insuffisant",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // Récupérer les données validées
        $recu = null;
        if ($request->hasFile('recu') && $request->file('recu')->isValid()) {
            $recu = $request->recu->store('files/recu/versement');
        }
        $receveur = $request->id_receveur;
        $montant = $request->montant;
        $id_caisse = $caisse_receveur->id;
        $add_by = $user->id;
        $note = "pour un Decaissement";

        // Nouveau decaissement
        $decaissement = new Versement ([
            'recu' => $recu,
            'correspondant' => $receveur,
            'montant' => $montant,
            'id_caisse' => $id_caisse,
            'add_by' => $add_by,
            'note' => $note
        ]);

        // creation du decaissement
        if ($decaissement->save()) {

            //notification du receveur
            $receveur = User::find($receveur);
            $receveur->notify(new Notif_recouvrement([
                'data' => $decaissement,
                'message' => "Nouveau decaissement de votre part"
            ]));

            //on debite le compte du receveur
            $caisse_receveur->solde = $caisse_receveur->solde - $montant;
            $caisse_receveur->save();

            //on debite le compte de la gestionnaire de flotte
            $caisse->solde = $caisse->solde - $montant;
            $caisse->save();
            $getionnaire_id = Auth::user()->id;
            $versements = Versement::All();
            $decaissements = [];
            foreach ($versements as $versement) {
                $id_caisse_gestionnaire = Caisse::where('id_user', $getionnaire_id)->first();
                if ($id_caisse_gestionnaire->id != $versement->id_caisse) {
                    $decaissements[] = [
                        'versement' => $versement,
                        'gestionnaire' => User::find($versement->add_by),
                        'receveur' => User::find($versement->correspondant),
                    ];
                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'versements' => $decaissements
                    ]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la Creation',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }


    /**
     * //Creer une passation de service
     */
    public function passation(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_receveur' => ['required', 'Numeric'], //id de l'utilisateur qui recoit l'argent
            'montant' => ['required', 'Numeric']
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

        //On verifi si le receveur est vraiment utilisateur
        if (!($receveur = User::find($request->id_receveur))) {
            return response()->json(
                [
                    'message' => "l'utilisateur en paramettre n'existe pas en BD",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //recuperer la caisse de l'utilisateur qui recoit le verssement
        $caisse_receveur = $receveur->caisse->first();

        //recuperer la caisse de l'utilisateur connecté
        $user = Auth::user();
        $caisse = $user->caisse->first();

        //On verifi si le compte du payeur est suffisant
        if ($caisse->solde < $request->montant) {
            return response()->json(
                [
                    'message' => "le solde du payeur est insuffisant",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // Récupérer les données validées
        $receveur = $request->id_receveur;
        $montant = $request->montant;
        $id_caisse = $caisse_receveur->id;
        $add_by = $user->id;
        $note = "pour une passation de service";

        // Nouveau decaissement
        $decaissement = new Versement ([
            'recu' => null,
            'correspondant' => $receveur,
            'montant' => $montant,
            'id_caisse' => $id_caisse,
            'add_by' => $add_by,
            'note' => $note
        ]);

        // creation du decaissement
        if ($decaissement->save()) {

            //notification du receveur
            $receveur = User::find($receveur);
            $receveur->notify(new Notif_recouvrement([
                'data' => $decaissement,
                'message' => "Nouvelle passation de service vers votre compte"
            ]));

            //on credite le compte du receveur
            $caisse_receveur->solde = $caisse_receveur->solde + $montant;
            $caisse_receveur->save();

            //on debite le compte de la gestionnaire de flotte
            $caisse->solde = $caisse->solde - $montant;
            $caisse->save();
            $getionnaire_id = Auth::user()->id;

            $versements = Versement::where('recu', null)->get()->filter(function (Versement $versement) {
                return $versement->add_by == Auth::user()->id || $versement->correspondant == Auth::user()->id;
            });;
            $decaissements = [];
            foreach ($versements as $versement) {
                    $decaissements[] = [
                        'versement' => $versement,
                        'emetteur' => User::find($versement->add_by),
                        'recepteur' => User::find($versement->correspondant),
                    ];
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'versements' => $decaissements
                    ]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la Creation',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * ////lister les Encaissements
     */
    public function encaissement_list()
    {
        $getionnaire_id = Auth::user()->id;
        $versements = Versement::All();
        $encaissements = [];
        foreach ($versements as $versement) {
            $id_caisse_gestionnaire = Caisse::where('id_user', $getionnaire_id)->first();
            if ($id_caisse_gestionnaire->id == $versement->id_caisse) {
                $encaissements[] = [
                    'versement' => $versement,
                    'gestionnaire' => User::find($versement->add_by),
                    'recouvreur' => User::find($versement->correspondant),
                ];
            }
        }
        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'versements' => $encaissements
                ]
            ]
        );
    }

    /**
     * ////lister les Decaissements
     */
    public function decaissement_list()
    {
        $getionnaire_id = Auth::user()->id;
        $versements = Versement::All();
        $decaissements = [];
        foreach ($versements as $versement) {
            $id_caisse_gestionnaire = Caisse::where('id_user', $getionnaire_id)->first();
            if ($id_caisse_gestionnaire->id != $versement->id_caisse) {
                $decaissements[] = [
                    'versement' => $versement,
                    'gestionnaire' => User::find($versement->add_by),
                    'recouvreur' => User::find($versement->correspondant),
                ];
            }
        }
        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'versements' => $decaissements
                ]
            ]
        );
    }

    /**
     * ////lister les passations de service
     */
    public function passations_list()
    {
        $versements = Versement::where('recu', null)->get()->filter(function (Versement $versement) {
            return $versement->add_by == Auth::user()->id || $versement->correspondant == Auth::user()->id;
        });
        $passations = [];
        foreach ($versements as $versement) {
            $passations[] = [
                'versement' => $versement,
                'emetteur' => User::find($versement->add_by),
                'recepteur' => User::find($versement->correspondant),
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'versements' => $passations
                ]
            ]
        );
    }

    /**
     * ////Details d'un verssement
     */
    public function versement_details($id)
    {
        $versement = Versement::find($id);

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => $versement
            ]
        );
    }


    /**
     * //Creer une depence.
     */
    public function depence(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'description' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'Numeric'],
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
        // Récupérer les données validées
        $description = $request->description;
        $montant = $request->montant;

        // Nouvelle depence
        $operation = new Operation ([
            'id_motif' => null,
            'id_user' => Auth::user()->id,
            'flux' => Statut::DEPENSE,
            'montant' => $montant,
            'description' => $description
        ]);

        // creation de La depence
        if ($operation->save()) {

            //Database Notification
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Recouvrement([
                        'data' => $operation,
                        'message' => "Nouvelle depence faite par un client"
                    ]));
                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Oppération créée',
                    'status' => true,
                    'data' => ['operation' => $operation]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la Creation',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * ////Details d'une depence
     */
    public function depence_details($id)
    {
        $depence = Operation::where('flux', Statut::DEPENSE)->where('id', $id)->first();
        $user = !is_null($depence) ? User::find($depence->id_user) : null;

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['depence' => $depence, 'user' => $user]
            ]
        );
    }

    /**
     * ////lister les depences par utilisateur
     */
    public function depence_user($id_utilisateur)
    {

        $depences = Operation::where('flux', Statut::DEPENSE)->where('id_user', $id_utilisateur)->get();
        $all_depence = [];
        foreach ($depences as $depence) {
            $all_depence[] = [
                'depences' => $depence,
                'user' => User::find($depence->id_user),
                'user' => !is_null($depence) ? User::find($depence->id_user) : null,
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'all_depence' => $all_depence
                ]
            ]
        );

    }

    /**
     * ////lister toutes les depences
     */
    public function depence_list()
    {
        $depences = Operation::where('flux', Statut::DEPENSE)->get();
        $all_depence = [];
        foreach ($depences as $depence) {
            $all_depence[] = [
                'depences' => $depence,
                'user' => User::find($depence->id_user),
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'all_depence' => $all_depence
                ]
            ]
        );
    }

    /**
     * //Creer une acquisition.
     */
    public function acquisition(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'description' => ['required', 'string', 'max:255'],
            'montant' => ['required', 'Numeric'],
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
        // Récupérer les données validées
        $description = $request->description;
        $montant = $request->montant;

        // Nouvelle acquisition
        $operation = new Operation ([
            'id_motif' => null,
            'id_user' => Auth::user()->id,
            'flux' => Statut::ACQUISITION,
            'montant' => $montant,
            'description' => $description
        ]);

        // creation de La acquisition
        if ($operation->save()) {

            //On implique la dette
            $caisse = Auth::user()->caisse->first();
            $caisse->solde = $caisse->solde - $montant;
            $caisse->save();

            //Database Notification
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Recouvrement([
                        'data' => $operation,
                        'message' => "Nouvelle depence faite par un client"
                    ]));
                }
            }

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Oppération créée',
                    'status' => true,
                    'data' => ['operation' => $operation]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la Creation',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * ////Details d'une acquisition
     */
    public function acquisition_details($id)
    {
        $acquisition = Operation::where('flux', Statut::ACQUISITION)->where('id', $id)->first();

        $user = !is_null($acquisition) ? User::find($acquisition->id_user) : null;

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['acquisitions' => $acquisition, 'user' => $user]
            ]
        );
    }

    /**
     * ////lister les acquisitions par acquisitions
     */
    public function acquisition_user($id_utilisateur)
    {

        $acquisitions = Operation::where('flux', Statut::ACQUISITION)->where('id_user', $id_utilisateur)->get();
        $all_acquisitions = [];
        foreach ($acquisitions as $acquisition) {
            $all_acquisitions[] = [
                'acquisition' => $acquisition,
                'user' => !is_null($acquisition) ? User::find($acquisition->id_user) : null,
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'all_acquisitions' => $all_acquisitions
                ]
            ]
        );
    }

    /**
     * ////lister toutes les acquisitions
     */
    public function acquisition_list()
    {
        $acquisitions = Operation::where('flux', Statut::ACQUISITION)->get();
        $all_acquisitions = [];
        foreach ($acquisitions as $acquisition) {
            $all_acquisitions[] = [
                'acquisition' => $acquisition,
                'user' => !is_null($acquisition) ? User::find($acquisition->id_user) : null,
            ];
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => [
                    'acquisitions' => $all_acquisitions
                ]
            ]
        );
    }
}

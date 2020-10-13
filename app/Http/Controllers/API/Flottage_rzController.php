<?php

namespace App\Http\Controllers\API;

use App\User;
use App\Puce;
use App\Role;
use App\Type_puce;
use App\Enums\Roles;
use App\Enums\Statut;
use App\Flottage_interne;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Notifications\Flottage as Notif_flottage;

class Flottage_rzController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct(){
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $rz = Roles::RECOUVREUR;
        $this->middleware("permission:$superviseur|$ges_flotte|$rz");
    }

    //creer le flottage
    Public function store(Request $request) {

        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'montant' => ['required', 'numeric'],
            'id_puce_from' => ['required', 'numeric'],
            'id_puce_to' => ['required', 'numeric'],
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
        
        // On verifi que la puce passée en paramettre existe
        if (!is_null(Puce::find($request->id_puce_from)) && !is_null(Puce::find($request->id_puce_to))) {
            
            //On recupère la puce qui envoie
            $puce_from = Puce::find($request->id_puce_from);

            //On recupère la puce qui recoit
            $puce_to = Puce::find($request->id_puce_to);

            //on recupère les types de la puce qui envoie
            $type_puce_from = Type_puce::find($puce_from->type)->name;

            //on recupère les types de la puce qui recoit
            $type_puce_to = Type_puce::find($puce_to->type)->name;


            //On se rassure que les puces passée en paramettre respectent toutes les conditions
            if ($type_puce_from != Statut::FLOTTAGE || $type_puce_to != Statut::PUCE_RZ) {
                return response()->json(
                    [
                        'message' => "Choisier des puces valide pour la transation",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

        }else {
            return response()->json(
                [
                    'message' => "une ou plusieurs puces entrées n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        

        //On se rassure que le solde est suffisant
        if ($puce_from->solde < $request->montant) {
            return response()->json(
                [
                    'message' => "le montant est insuffisant",
                    'status' => false,
                    'data' => null
                ]
            );
        }
        
        //on debite le solde du gestionnaire de flotte
        $puce_from->solde = $puce_from->solde - $request->montant;

        //On credite le solde du rz
        $puce_to->solde = $puce_to->solde + $request->montant;

        //On debite la caisse du rz
        $rz = $puce_to->rz->caisse->first();
        $rz->solde = $rz->solde - $request->montant; 

        $gestionnaire = Auth::user();

        // Nouveau flottage
        $flottage_rz = new Flottage_interne([
            'id_user' => $gestionnaire->id,
            'id_sim_from' => $puce_from->id,
            'id_sim_to' => $puce_to->id,
            'reference' => null,
            'statut' => Statut::EFFECTUER,
            'note' => null,
            'montant' => $request->montant,
            'reste' => null
        ]);
        
        //si l'enregistrement du flottage a lieu
        if ($flottage_rz->save()) {

            $puce_from->save();
            $puce_to->save();
            $rz->save();

            $role = Role::where('name', Roles::RECOUVREUR)->first();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_flottage([
                        'data' => $flottage_rz,
                        'message' => "Nouveau flottage Dans votre puce"
                    ]));
                }
            }

            //On recupere les Flottages rz
            $flottage_internes = Flottage_interne::get();

            $flottages = [];

            foreach($flottage_internes as $flottage_interne) {

                $puce_gf = Puce::find($flottage_interne->id_sim_from );
                if ($puce_gf->type_puce->name == Statut::FLOTTAGE) {

                    //recuperer la puce d'envoie
                    $puce_emetrice = Puce::find($flottage_interne->id_sim_from);

                    //recuperer la puce de reception
                    $puce_receptrice = Puce::find($flottage_interne->id_sim_to);

                    //recuperer celui qui a éffectué le flottage
                    $rz = User::find($flottage_interne->id_user);


                    $flottages[] = [
                        'puce_receptrice' => $puce_receptrice,
                        'puce_emetrice' => $puce_emetrice,
                        'rz' => $rz,
                        'flottage' => $flottage_interne
                    ];
                }
            }

                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => "Le flottage c'est bien passé",
                        'status' => true,
                        'data' => ['flottages' => $flottages]
                    ]
                );
        }else {

            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors du flottage',
                    'status' => false,
                    'data' => null
                ]
            );

        }

    }


    /**
     * ////lister tous les flottages rz
     */
    public function list_all()
    {
        //On recupere les Flottages
        $flottage_internes = Flottage_interne::get();

        $flottages = [];

        foreach($flottage_internes as $flottage_interne) {

            //recuperer la puce d'envoie
            $puce_emetrice = Puce::find($flottage_interne->id_sim_from);
            if ($puce_emetrice->type_puce->name == Statut::FLOTTAGE) {

                //recuperer la puce de reception
                $puce_receptrice = Puce::find($flottage_interne->id_sim_to);

                //recuperer celui qui a éffectué le flottage
                $rz = User::find($flottage_interne->id_user);


                $flottages[] = [
                    'puce_receptrice' => $puce_receptrice,
                    'puce_emetrice' => $puce_emetrice,
                    'rz' => $rz,
                    'flottage' => $flottage_interne
                ];
            }
        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottages' => $flottages]
            ]
        );
    }


    /**
     * ////details d'un flottages interne
     */
    public function show($id_flottage)
    {

        if (!Flottage_interne::Find($id_flottage)){

            return response()->json(
                [
                    'message' => "le flottage specifié n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }
        //On recupere le Flottage
        $flottage = Flottage_interne::find($id_flottage);

        //recuperer celui qui a éffectué le flottage
        $rz = User::find($flottage->id_user);

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottage' => $flottage,'rz' => $rz ]
            ]
        );

    }


}

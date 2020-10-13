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

class Flottage_interneController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct(){
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$superviseur|$ges_flotte");
    }

    /**
     * @param Request $request
     * @return JsonResponse
     */
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
        if (Puce::find($request->id_puce_from) && Puce::find($request->id_puce_to)) {

            //On recupère la puce qui envoie
            $puce_from = Puce::find($request->id_puce_from);

            //On recupère la puce qui recoit
            $puce_to = Puce::find($request->id_puce_to);

            //on recupère les types de la puce qui envoie
            $type_puce_from = Type_puce::find($puce_from->type)->name;

            //on recupère les types de la puce qui recoit
            $type_puce_to = Type_puce::find($puce_to->type)->name;

            //On se rassure que les puces passée en paramettre respectent toutes les conditions
            if ($type_puce_from != Statut::FLOTTAGE_SECONDAIRE || $type_puce_to != Statut::FLOTTAGE) {
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
        if ($puce_from->solde <= $request->montant) {
            return response()->json(
                [
                    'message' => "le montant est insuffisant",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //on debite le solde du supperviseur
        $puce_from->solde = $puce_from->solde - $request->montant;

        //On credite le solde de la GF
        $puce_to->solde = $puce_to->solde + $request->montant;

        //Le supperviseur
        $supperviseur = Auth::user();

        // Nouveau flottage
        $flottage_interne = new Flottage_interne([
            'id_user' => $supperviseur->id,
            'id_sim_from' => $puce_from->id,
            'id_sim_to' => $puce_to->id,
            'reference' => null,
            'statut' => Statut::EFFECTUER,
            'note' => null,
            'montant' => $request->montant,
            'reste' => null
        ]);

        //si l'enregistrement du flottage a lieu
        if ($flottage_interne->save()) {

            $puce_from->save();
            $puce_to->save();

            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();

            //Database Notification
            $users = User::all();
            foreach ($users as $user) {

                if ($user->hasRole([$role->name])) {

                    $user->notify(new Notif_flottage([
                        'data' => $flottage_interne,
                        'message' => "Nouveau flottage Interne"
                    ]));
                }
            }

            //On recupere les Flottages
            $flottage_internes = Flottage_interne::get();

            $flottages = [];

            foreach($flottage_internes as $flottage_interne) {

                //recuperer la puce du superviseur
                $puce_emetrice = Puce::find($flottage_interne->id_sim_from);

                if ($puce_emetrice->type_puce->name == Statut::FLOTTAGE_SECONDAIRE) {

                    //recuperer la puce du gestionnaire de flotte
                    $puce_receptrice = Puce::find($flottage_interne->id_sim_to);

                    //recuperer celui qui a éffectué le flottage
                    $superviseur = User::find($flottage_interne->id_user);


                    $flottages[] = [
                        'puce_receptrice' => $puce_receptrice,
                        'puce_emetrice' => $puce_emetrice,
                        'superviseur' => $superviseur,
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
     * ////lister tous les flottages interne
     */
    public function list_all()
    {
        //On recupere les Flottages
        $flottage_internes = Flottage_interne::get();

        $flottages = [];

        foreach($flottage_internes as $flottage_interne) {

            //recuperer la puce du superviseur
            $puce_emetrice = Puce::find($flottage_interne->id_sim_from);

            if ($puce_emetrice->type_puce->name == Statut::FLOTTAGE_SECONDAIRE) {

                //recuperer la puce du gestionnaire de flotte
                $puce_receptrice = Puce::find($flottage_interne->id_sim_to);

                //recuperer celui qui a éffectué le flottage
                $superviseur = User::find($flottage_interne->id_user);


                $flottages[] = [
                    'puce_receptrice' => $puce_receptrice,
                    'puce_emetrice' => $puce_emetrice,
                    'superviseur' => $superviseur,
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
        $superviseur = User::find($flottage->id_user);

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['flottage' => $flottage,'superviseur' => $superviseur ]
            ]
        );

    }
}

<?php

namespace App\Http\Controllers\API;

use App\Puce;
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
use App\Http\Resources\Destockage as DestockageResource;

class ApprovisionnementEtpController extends Controller
{
    /**
     * les conditions de lecture des methodes

     */
    function __construct()
    {
        $recouvreur = Roles::RECOUVREUR;
        $agent = Roles::AGENT;
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
                        'message' => ['error'=>$validator->errors()],
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

                //Notification
                $role = Role::where('name', Roles::AGENT)->first();    
                $event = new NotificationsEvent($role->id, ['message' => 'Une demande traitée']);
                broadcast($event)->toOthers();
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
                        'message' => ['error'=>$validator->errors()],
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

                //Notification
                $role = Role::where('name', Roles::AGENT)->first();    
                $event = new NotificationsEvent($role->id, ['message' => 'Une demande revoquée']);
                broadcast($event)->toOthers();

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
            return response()->json(
                [
                    'message' => ['error'=>$validator->errors()],
                    'status' => false,
                    'data' => null
                ]
            );
        }

        //au cas ou le type est BY_AGENT, on est sencé recevoir l'id de l'agent. on verifi que l'id recu est bien un Agent
        if (isset($request->id_agent)) {
            //on verifi si l'agent existe
            if (!($agent = Agent::find($request->id_agent))) {
                return response()->json(
                    [
                        'message' => "Entrer un Agent valide",
                        'status' => false,
                        'data' => null
                    ]
                );
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

        //initier le destockage encore appelé approvisionnement de ETP
        $destockage = new Destockage([
            'id_recouvreur' => isset($request->id_recouvreur) ? $request->id_recouvreur : Auth::user()->id,
            'type' => $type,
            'id_puce' => $id_puce,
            'id_agent' => isset($request->id_agent) ? $id_agent : null,
            'fournisseur' => isset($request->fournisseur) ? $fournisseur : null,
            'recu' => $recu,
            'reference' => null,
            'statut' => Statut::EN_COURS,
            'note' => null,
            'montant' => $montant
        ]);

        if ($destockage->save()) {

            //Notification
            $role = Role::where('name', Roles::GESTION_FLOTTE)->first();    
            $event = new NotificationsEvent($role->id, ['message' => 'Nouvel approvisionnement de ETP']);
            broadcast($event)->toOthers();

            //la puce de ETP concernée et on credite
            $puce_etp = Puce::find($request->id_puce);
            $puce_etp->solde = $puce_etp->solde + $montant;
            $puce_etp->save();

            if (isset($request->id_agent)) {

                //recherche de la flotte concerné
                $id_flotte = Puce::find($request->id_puce)->flote->id;



                //On recupère la puce de l'agent concerné et on debite
                $puce_agent = Puce::where('id_agent', $request->id_agent)->where('id_flotte', $id_flotte)->first();

                if ($puce_agent == null){

                    // Renvoyer une erreur
                    return response()->json(
                        [
                            'message' => "cet agent n'a pas de puce abilité à effectuer un destockage vers la puce ETP selectionnée",
                            'status'=>false,
                            'data' => null
                        ]
                    );

                }

                $puce_agent->solde = $puce_agent->solde - $montant;
                $puce_agent->save();

                //On recupère la caisse de l'agent concerné et on credite
                $caisse = Caisse::where('id_user', $agent->user->id)->first();
                $caisse->solde = $caisse->solde + $montant;
                $caisse->save();

            }

            $recouvreur_id = Auth::user()->id;
            if($type == Statut::BY_AGENT) $destockages = Destockage::where('type', Statut::BY_AGENT)->get();
            else $destockages = Destockage::where('type', Statut::BY_DIGIT_PARTNER)->orWhere('type', Statut::BY_BANK)->get();

            return response()->json(
                [
                    'message' => "liste",
                    'status' => true,
                    'data' => DestockageResource::collection($destockages->filter(function(Destockage $destockage) use ($recouvreur_id) {
                        return ($destockage->id_recouvreur == $recouvreur_id);
                    }))
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
     * ////approuver une demande de destockage
     */
    public function approuve($id)
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

        //on approuve le destockage
        $destockage->statut = Statut::EFFECTUER;

        //message de reussite
        if ($destockage->save()) {
            $destockages = Destockage::where('type', Statut::BY_AGENT)->get();

            return response()->json(
                [
                    'message' => "liste",
                    'status' => true,
                    'data' => DestockageResource::collection($destockages)
                ]
            );
        }else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la confirmation',
                    'status'=>false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * ////approuver un approvisionnement
     */
    public function approuve_approvisionnement($id)
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

        //on approuve le destockage
        $destockage->statut = Statut::EFFECTUER;

        //message de reussite
        if ($destockage->save()) {
            $destockages = Destockage::where('type', Statut::BY_DIGIT_PARTNER)->orWhere('type', Statut::BY_BANK)->get();

            return response()->json(
                [
                    'message' => "liste",
                    'status' => true,
                    'data' => DestockageResource::collection($destockages)
                ]
            );
        }else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la confirmation',
                    'status'=>false,
                    'data' => null
                ]
            );
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
        $destockages = Destockage::where('type', Statut::BY_DIGIT_PARTNER)->orWhere('type', Statut::BY_BANK)->get();
        return response()->json(
            [
                'message' => "liste",
                'status' => true,
                'data' => DestockageResource::collection($destockages)
            ]
        );
    }

    /**
     * ////lister les approvisionnements par un responsable de zone
     */
    public function list_all_collector($id)
    {
        $destockages = Destockage::where('type', Statut::BY_DIGIT_PARTNER)->orWhere('type', Statut::BY_BANK)->get();
        return response()->json(
            [
                'message' => "liste",
                'status' => true,
                'data' => DestockageResource::collection($destockages->filter(function(Destockage $destockage) use ($id) {
                    return ($destockage->id_recouvreur == $id);
                }))
            ]
        );
    }

    // BY_AGENT
    /**
     * ////lister les destockages
     */
    public function list_all_destockage()
    {
        $destockages = Destockage::where('type', Statut::BY_AGENT)->get();
        return response()->json(
            [
                'message' => "liste",
                'status' => true,
                'data' => DestockageResource::collection($destockages)
            ]
        );
    }

    /**
     * ////lister les destockages par un responsable de zone
     */
    public function list_all_destockage_collector($id)
    {
        $destockages = Destockage::where('type', Statut::BY_AGENT)->get();
        return response()->json(
            [
                'message' => "liste",
                'status' => true,
                'data' => DestockageResource::collection($destockages->filter(function(Destockage $destockage) use ($id) {
                    return ($destockage->id_recouvreur == $id);
                }))
            ]
        );
    }

    /**
     * ////lister les destockages par un agent
     */
    public function list_all_destockage_agent($id)
    {
        $destockages = Destockage::where('type', Statut::BY_AGENT)->get();
        $agent = Agent::where(['id_user' => $id])->first();
        return response()->json(
            [
                'message' => "liste",
                'status' => true,
                'data' => DestockageResource::collection($destockages->filter(function(Destockage $destockage) use ($agent) {
                    return ($destockage->id_agent == $agent->id);
                }))
            ]
        );
    }

}

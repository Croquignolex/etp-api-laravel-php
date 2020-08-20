<?php

namespace App\Http\Controllers\API;

use App\Http\Resources\Retour_flote as Retour_floteResource;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Approvisionnement;
use App\Retour_flote;
use App\Demande_flote;
use App\Recouvrement;
use App\Enums\Roles;
use App\Type_puce;
use App\Caisse;
use App\Flote;
use App\Agent;
use App\User;
use App\Puce;


class Retour_flotteController extends Controller
{

    /**

     * les conditions de lecture des methodes

     */

    function __construct(){

        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte");

    }

    /**
     * ////Faire un retour de flotte
     */
    public function retour(Request $request)
    {
        
        // Valider données envoyées
        $validator = Validator::make($request->all(), [       
            'puce_agent' => ['required', 'Numeric'], 
            'puce_flottage' => ['required', 'Numeric'], 
            'id_flottage' => ['required', 'Numeric'],        
            'recu' => ['nullable', 'file', 'max:10000'],
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
        
        
        //On verifi si le flottage passée existe réellement
            if (!Approvisionnement::find($request->id_flottage)) {
                return response()->json(
                    [
                        'message' => "le flottage n'existe pas",
                        'status' => false,
                        'data' => null
                    ]
                );
            }
            

        //On verifi que le montant n'est pas supperieur au montant demandé
            $flottage = Approvisionnement::find($request->id_flottage);
            if ($flottage->reste < $request->montant) {
                return response()->json(
                    [
                        'message' => "Vous essayez de recouvrir plus d'argent que prevu",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

            

        //On verifi si la puce passée appartien à l'agent concerné
            //L'agent concerné
            $user = User::Find($flottage->demande_flote->id_user);
            $agent = Agent::Where('id_user', $user->id)->first();
            $puce_agent = Puce::Find($request->puce_agent);
            $puce_flottage = Puce::Find($request->puce_flottage);
 
            //On verifi que le montant n'est pas supperieur au montant demandé
            if ($puce_agent == null || $puce_flottage == null) {
                return response()->json(
                    [
                        'message' => "Lune des puce entré n'existe pas",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

            if ($agent->id != $puce_agent->id_agent) {
                return response()->json(
                    [
                        'message' => "Vous devez renvoyer la flotte avec une puce appartenant à l'agent qui a été flotté",
                        'status' => false,
                        'data' => null
                    ]
                );
            }
            
            //On verifi si les puce passée appartien à au meme oppérateur de flotte            
            if ($puce_flottage->flote->nom != $puce_agent->flote->nom) {
                return response()->json(
                    [
                        'message' => "Vous devez renvoyer la flotte à une puce du meme opérateur",
                        'status' => false,
                        'data' => null
                    ]
                );
            }

        //On verifi que le retour flote est fait ver une puce apte à flotter
        $type_puce = $puce_flottage->type_puce->name;
        if ($type_puce != \App\Enums\Statut::FLOTTAGE && $type_puce != \App\Enums\Statut::FLOTTAGE_SECONDAIRE) {
            return response()->json(
                [
                    'message' => "vous ne pouvez renvoyer la flotte qu'à une puce agent",
                    'status' => false,
                    'data' => null
                ]
            );
        }
        
        //On recupère les données validés
        
            //enregistrer le recu
            $recu = null;
            if ($request->hasFile('recu') && $request->file('recu')->isValid()) {
                $recu = $request->recu->store('recu');
            }
            $montant = $request->montant; 
                
            //recouvreur
            $recouvreur = Auth::user();       
            
            
        //initier le retour flotte 
        $retour_flotte = new Retour_flote([
            'id_user' => $recouvreur->id,
            'reference' => null,
            'montant' => $montant,
            'reste' => $montant,
            'id_approvisionnement' => $request->id_flottage,
            'statut' => \App\Enums\Statut::EN_COURS,
            'user_destination' => $puce_flottage->id,
            'user_source' => $puce_agent->id
        ]);


        if ($retour_flotte->save()) {

            //on credite la puce de ETP concernée 
            $puce_flottage->solde = $puce_flottage->solde + $montant;                    
            $puce_flottage->save();
            
            //On recupère la puce de l'agent concerné et on debite
            $puce_agent->solde = $puce_agent->solde - $montant;
            $puce_agent->save();

            //On credite la caisse de l'Agent pour le remboursement de la flotte recu, ce qui implique qu'il rembource ses detes à ETP
            //Caisse de l'agent concerné
            $caisse = $user->caisse->first(); 
            $caisse->solde = $caisse->solde + $montant;
            $caisse->save();
            
            //On calcule le reste à recouvrir
            $flottage->reste = $flottage->reste - $montant;

            //On change le statut du flottage
            if ($flottage->reste == 0) {

                $flottage->statut = \App\Enums\Statut::TERMINEE ;

            }else {

                $flottage->statut = \App\Enums\Statut::EN_COURS ;

            }

            //Enregistrer les oppérations
            $flottage->save();               

            //Renvoyer les données de succes
            return new Retour_floteResource($retour_flotte);

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
     * ////lister tous les retour flotte
     */
    public function list_all()
    {

        //On recupere les retour flotte
        $retour_flotes = Retour_flote::get();

        foreach($retour_flotes as $retour_flote) {

            //recuperer le flottage correspondant
            $flottage = Approvisionnement::find($retour_flote->id_approvisionnement);

            //recuperer celui qui a éffectué le retour flotte
                $user = User::Find($retour_flote->id_user);

            //recuperer la puce de l'agent
                $puce_agent = Puce::find($retour_flote->id_user);
                
            //recuperer l'agent concerné 
                $agent = $puce_agent->agent;

            $retours_flotes[] = ['retour_flote' => $retour_flote,'flottage' => $flottage, 'user' => $user, 'agent' => $agent, 'puce_agent' => $puce_agent,];

        }

        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['retours_flotes' => $retours_flotes]
            ]
        );

    }


    /**
     * ////lister les retour flotte d'un flottage
     */
    public function list_retour_flotte($id)
    {
        if (!Approvisionnement::Find($id)){

            return response()->json(
                [
                    'message' => "le flottage n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }

        //On recupere les retour flotte
        $retour_flotes = Retour_flote::where('id_approvisionnement', $id)->get();

        
        return response()->json(
            [
                'message' => '',
                'status' => true,
                'data' => ['retour_flotes' => $retour_flotes]
            ]
        );

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
    public function list_retour_flotte_by_agent($id)
    {
        if (!$agent = Agent::Find($id)){

            return response()->json(
                [
                    'message' => "l'agent' n'existe pas",
                    'status' => true,
                    'data' => []
                ]
            );
        }
        $user = User::find($agent->id_user);

        // $retour_flottes = DB::table('retour_flotes')
        //     ->join('approvisionnements', 'approvisionnements.id', '=', 'retour_flotes.id_approvisionnement')
        //     ->join('demande_flotes', 'demande_flotes.id', '=', 'approvisionnements.id_demande_flote')
        //     ->join('users', 'users.id', '=', 'demande_flotes.id_user')
        //     ->select('retour_flotes.*')
        //     ->where('users.id', $user->id)
        //     ->get();

        $retour_flottes = Retour_flote::get()->filter(function(Retour_flote $retour_flote) use ($user){
            $demande_flote =$retour_flote->flotage->demande_flote;
            $id_user = $demande_flote->id_user;
            return $id_user == $user->id;
        });

        
        return Retour_floteResource::collection($retour_flottes); 

    }



}

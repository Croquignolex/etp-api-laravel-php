<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Agent;
use App\User;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;

class AgentController extends Controller
{


    /**

     * les conditions de lecture des methodes

     */

    function __construct()

    {

         $this->middleware('role:Admin'); 

    }



    /**
     * creer un Agent
     */
    public function store(Request $request)
    { 

        // l'utilisateur connecté
        $id_crator = auth()->user()->id;

        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'name' => ['required', 'string', 'max:255'],
            'img_cni' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif|max:10000'],
            'phone' => ['required', 'integer'],
            'id_user' => ['required', 'integer', 'unique:agents,id_user'], //un compte agent correspond oubligatoirement à un utilisateur
            'reference' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email'],
            'taux_commission' => ['required', 'Numeric'],
            'pays' => ['required', 'string', 'max:255'],
            'adresse' => ['required', 'string', 'max:255']
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
   

        

        if (!User::Find($request->id_user)) {
            return response()->json(
                [
                    'message' => 'l\'utilisateur passé n\'existe pas.',
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // Récupérer les données validées
        $name = $request->name;
        $img_cni = null;
        if ($request->hasFile('img_cni') && $request->file('img_cni')->isValid()) {
            $img_cni = $request->img_cni->store('images/agents');
        }
        $phone = $request->phone;
        $reference = $request->reference;
        $email = $request->email;      
        $taux_commission = $request->taux_commission;
        $pays = $request->pays;
        $id_user = $request->id_user;
        $adresse = $request->adresse;


        // Nouvel agent
        $Agent = new Agent([
            'id_creator' => $id_crator,
            'id_user' => $id_user,
            'nom' => $name,
            'img_cni' => $img_cni,
            'phone' => $phone,
            'reference' => $reference,
            'email' => $email,
            'taux_commission' =>  $taux_commission,
            'pays' => $pays,
            'adresse' => $adresse
        ]);

        // creation de l'agent
        if ($Agent->save()) {

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'agent cree',
                    'status' => true,
                    'data' => ['Agent' => $Agent]
                ]
            );

        } else {
            // Renvoyer une erreur
            
            return response()->json(
                [
                    'message' => 'erreur lors de la modification',
                    'status' => false,
                    'data' => null
                ]
            );
            
        } 

    }



    /**
     * details d'un Agent
     */
    public function show($id)
    {
        //on recherche l'agent en question
        $agent = Agent::find($id);


        //Envoie des information
        if(agent::find($id)){

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['Agent' => $agent]
                ]
            );

        }else{

            return response()->json(
                [
                    'message' => 'cet agent n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
        }
         
    }



    /**
     * Modifier un Agent
     */
    public function edit(Request $request, $id)
    {
        
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'name' => ['required', 'string', 'max:255'],
            'img_cni' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif|max:10000'],
            'phone' => ['required', 'integer'],
            'reference' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email'],
            'taux_commission' => ['required', 'Numeric'],
            'pays' => ['required', 'string', 'max:255'],
            'adresse' => ['required', 'string', 'max:255']
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

        // Récupérer les données validées
        $name = $request->name;
        $img_cni = null;
        if ($request->hasFile('img_cni') && $request->file('img_cni')->isValid()) {
            $img_cni = $request->img_cni->store('images/agents');
        }
        $phone = $request->phone;
        $reference = $request->reference;
        $email = $request->email;      
        $taux_commission = $request->taux_commission;
        $pays = $request->pays;
        $adresse = $request->adresse;

        // rechercher l'agent
        $agent = Agent::find($id);

        // Modifier le profil de l'utilisateur
        $agent->nom = $name;
        if ($img_cni != null) {
            $agent->img_cni = $img_cni;
        }
        $agent->phone = $phone;
        $agent->reference = $reference;
        $agent->email = $email;
        $agent->taux_commission = $taux_commission;
        $agent->pays = $pays;
        $agent->adresse = $adresse;


        if ($agent->save()) {
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'agent modifié',
                    'status' => true,
                    'data' => ['Agent' => $agent]
                ]
            );
            
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la modification', 
                    'status'=>false,
                    'data' => null
                ]
            );
        } 

    }


    /** 
     * liste des Agents
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function list()
    {

        if (Agent::where('deleted_at', null)) {
            $agents = Agent::where('deleted_at', null)->get();
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['agents' => $agents]
                ]
            );
         }else{
            return response()->json(
                [
                    'message' => 'erreur lors de la modification', 
                    'status'=>false,
                    'data' => null
                ]
            );
         }
        
    } 


    /** 
     * supprimer un Agents
     * 
     * @return \Illuminate\Http\Response 
     */ 
    public function delete($id)
    {
        if (Agent::find($id)) {
            $agent = Agent::find($id);
            $agent->deleted_at = now();
            if ($agent->save()) {

                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => 'agent archivé',
                        'status' => true,
                        'data' => null
                    ]
                );
            } else {
                // Renvoyer une erreur
                return response()->json(
                    [
                        'message' => 'erreur lors de l archivage', 
                        'status'=>false,
                        'data' => null
                    ]
                );
            } 
         }else{
            return response()->json(
                [
                    'message' => 'cet agent n existe pas', 
                    'status'=>false,
                    'data' => null
                ]
            );
         }
        
    }


}

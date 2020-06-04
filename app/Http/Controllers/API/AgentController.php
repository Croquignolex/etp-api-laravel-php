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
        $id_user = auth()->user()->id;

        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'name' => ['required', 'string', 'max:255'],
            'img_cni' => ['nullable', 'image', 'mimes:jpeg,jpg,png,gif|max:10000'],
            'phone' => ['required', 'integer'],
            'reference' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email'],
            'taux_commission' => ['required', 'Numeric'],
            'pays' => ['required', 'string', 'max:255'],
            'adresse' => ['required', 'string', 'max:255']
        ]);
        if ($validator->fails()) { 
                    return response()->json(['error'=>$validator->errors(), 'status'=>401], 401);            
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


        // Nouvel agent
        $Agent = new Agent([
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
                    'Agent' => $Agent,
                    'success' => 'true', 
                    'status'=>200,
                ],
                200
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la modification',
                    'status'=>500,
                    'success' => 'false'
                ],
                500
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
        if(Agent::find($id)){

            return response()->json(['success' => $agent, 'status'=>200]);

        }else{

            return response()->json(['error' => 'cet agent n existe pas', 'status'=>204]);
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
            'reference' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email'],
            'taux_commission' => ['required', 'Numeric'],
            'pays' => ['required', 'string', 'max:255'],
            'adresse' => ['required', 'string', 'max:255']
        ]);
        if ($validator->fails()) { 
                    return response()->json(['error'=>$validator->errors(), 'status'=>401], 401);            
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
                    'agent' => $agent, 
                    'status'=>200,
                    'success' => 'true',
                ],
                200
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la modification', 
                    'status'=>500,
                    'success' => 'false'
                ],
                500
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
            return response()->json(['success' => $agents, 'status'=>200]);
         }else{
            return response()->json(['error' => 'pas d agent a lister', 'status'=>204]); 
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
                        'agent' => $agent, 
                        'status'=>200,
                        'success' => 'true',
                    ],
                    200
                );
            } else {
                // Renvoyer une erreur
                return response()->json(
                    [
                        'message' => 'erreur lors de l archivage', 
                        'status'=>500,
                        'success' => 'false'
                    ],
                    500
                );
            } 
         }else{
            return response()->json(['error' => 'cet agent n existe pas']); 
         }
        
    }


}

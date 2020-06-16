<?php

namespace App\Http\Controllers\API;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Agent;
use App\User;
use App\Utiles\ImageFromBase64;
use App\Enums\Statut;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class AgentController extends Controller
{


    /**

     * les conditions de lecture des methodes

     */

    function __construct()

    {

        $this->middleware('permission:Superviseur');

    }



    /**
     * creer un Agent
     */
    public function store(Request $request)
    { 

        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 

            //user informations
                'name' => 'required',
                'phone' => 'required|numeric|unique:users,phone',
                'adresse' => 'nullable',
                'description' => 'nullable',
                'poste' => ['nullable', 'string', 'max:255'],
                'email' => 'required|email|unique:users,email', 
                'password' => 'required|string|min:6', 

            //Agent informations
                'base_64_image' => 'nullable|string',
                'reference' => ['nullable', 'string', 'max:255'],
                'taux_commission' => ['required', 'Numeric'],
                'ville' => ['required', 'string', 'max:255'],
                'pays' => ['required', 'string', 'max:255']    

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
            // users
                $name = $request->name;
                $phone = $request->phone;
                $adresse = $request->adresse;
                $description = $request->description;      
                $poste = $request->poste;
                $email = $request->email;
                $password = bcrypt($request->password);                
                $roles = 'Agent';

                

            // Agent                
                $reference = $request->reference;
                $taux_commission = $request->taux_commission;
                $ville = $request->ville;      
                $pays = $request->pays; 
                $img_cni = null;              

                if (isset($request->base_64_image)) {
                    $img_cni = $request->base_64_image;
                    // Convert base 64 image to normal image for the server and the data base
                    $server_image_name_path = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image'), 
                    'images/avatars/');
                    $img_cni = $server_image_name_path;
                }        


        //l'utilisateur connecté
            $add_by_id = Auth::user()->id;
            
        // Nouvel utilisateur
            $user = new User([
                'add_by' => $add_by_id,
                'poste' => $poste,
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'phone' => $phone,
                'adresse' => $adresse,
                'description' => $description
            ]);

        if ($user->save()) {

            $user->assignRole($roles);
            $user = User::find($user->id);
            //info user à renvoyer
                $success['token'] =  $user->createToken('MyApp')-> accessToken;
                $success['user'] =  $user;

            // Nouvel Agent
                $agent = new Agent([
                    'id_creator' => $add_by_id,
                    'id_user' => $user->id,
                    'img_cni' => $img_cni,
                    'reference' => $reference,
                    'taux_commission' => $taux_commission,
                    'ville' => $ville,
                    'pays' => $pays
                ]);
                
                
                if ($agent->save()) {

                    $success['agent'] =  $agent;

                    // Renvoyer un message de succès
                    return response()->json(
                        [
                            'message' => 'agent cree',
                            'status' => true,
                            'data' => ['success' => $success]
                        ]
                    );

                } else {
                    // Renvoyer une erreur
                    
                    return response()->json(
                        [
                            'message' => 'erreur lors de la creation',
                            'status' => false,
                            'data' => null
                        ]
                    );
                    
                } 

        }else {
            // Renvoyer un message de erreur
            return response()->json(
                [
                    'message' => 'Problème lors de la creation de l utilisateur correspondant',
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
            $user = User::find($agent->id_user);
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['agent' => $agent, 'user' => $user]
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
            'reference' => ['nullable', 'string', 'max:255'],
            'taux_commission' => ['required', 'Numeric'],
            'ville' => ['required', 'string', 'max:255'],
            'pays' => ['required', 'string', 'max:255'] 
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
        $reference = $request->reference;
        $taux_commission = $request->taux_commission;
        $ville = $request->ville;      
        $pays = $request->pays; 
        

        // rechercher l'agent
        $agent = Agent::find($id);

        // Modifier le profil de l'utilisateur
        $agent->reference = $reference;
        $agent->taux_commission = $taux_commission;
        $agent->ville = $ville;
        $agent->pays = $pays;


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

            foreach($agents as $agent) {

                $returenedAgents[] = ['agent' => $agent, 'user' => User::find($agent->id_user)];

            } 

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['agents' => $returenedAgents]
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

                $user = User::find($agent->id_user);
                $user->deleted_at = now();
                $user->save();

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

    /**
     * @param Base64ImageRequest $request
     * @return JsonResponse
     */
    public function edit_cni(Request $request, $id)
    {

        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'base_64_image' => 'required|string', 
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
        
        // Get current user
        $agent = Agent::find($id);
        $agent_img_cni_path_name =  $agent->img_cni;

        //Delete old file before storing new file
        if(Storage::exists($agent_img_cni_path_name) && $agent_img_cni_path_name != 'users/default.png')
            Storage::delete($agent_img_cni_path_name);


        // Convert base 64 image to normal image for the server and the data base
        $server_image_name_path = ImageFromBase64::imageFromBase64AndSave($request->input('base_64_image'),
            'images/avatars/');

        // Changer l' avatar de l'utilisateur
        $agent->img_cni = $server_image_name_path;

        // Save image name in database      
        if ($agent->save()) {
            return response()->json(
                [
                    'message' => 'Photo de profil mise à jour avec succès',
                    'status' => true,
                    'data' => ['user'=>$agent]
                ]
            );
        }else {
            return response()->json(
                [
                    'message' => 'erreur de modification de l avatar',
                    'status' => true,
                    'data' => ['user'=>$agent]
                ]
            );
        }
        
    }


}

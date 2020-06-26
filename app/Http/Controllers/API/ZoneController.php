<?php

namespace App\Http\Controllers\API;


namespace App\Http\Controllers\API;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Agent;
use App\Zone;
use App\Enums\Roles;
use App\Utiles\ImageFromBase64;
use Illuminate\Support\Facades\Validator;
use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Support\Facades\Auth;

class ZoneController extends Controller
{
    
    
            /**

     * les conditions de lecture des methodes

     */

    function __construct(){

        $this->middleware('permission:Superviseur');

    }
 

    /**
     * //Creer une zone.
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'map' => ['nullable', 'string'],
            'description' => ['nullable', 'string']
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
        $reference = $request->reference;
        $map = $request->map;
        $description = $request->description;


        // Nouvelle zone
        $zone = new Zone ([
            'nom' => $name,
            'reference' => $reference,
            'map' => $map,
            'description' => $description
        ]);

        // creation de La zone
        if ($zone->save()) {

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'zone créée',
                    'status' => true,
                    'data' => ['zone' => $zone]
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
     * //details d'une zone'
     */
    public function show($id)
    {
        //on recherche la zone en question
        $zone = Zone::find($id);
		
		$agents = [];
		$recouvreurs = [];
		
		$users = User::all(); 
		 foreach($users as $user) 
		 {
			 $userRole = $user->roles->pluck('name','name')->all();
			 if($userRole === Roles::AGENT) 
			 {
				  $user_zones = json_decode($user->id_zone);
				  if(array_search($zone->id, $user_zones)) $agents[] = $user;
			 }
			 else if($userRole === Roles::RECOUVREUR)
			 {
				 $user_zones = json_decode($user->id_zone);
				 if(array_search($zone->id, $user_zones)) $recouvreurs[] = $user;
			 }
		 }

        //Envoie des information
        if(Zone::find($id)){

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['zone' => $zone, 'agents' => $agents, 'recouvreurs' => $recouvreurs]
                ]
            );

        }else{

            return response()->json(
                [
                    'message' => 'ecette zone n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }


    /**
     * //Attribuer une zonne à un utilisateur'
     */
    public function give_zone(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'id_user' => ['required', 'Numeric'],
            'id_zone' => ['nullable', 'array']
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

        if (isset($request->id_zone)) {
            foreach ($request->id_zone as $zone) {
                // on verifie si la zone est définie
                
                    if (!Zone::Find($zone)) {
                        return response()->json(
                            [
                                'message' => 'une zone au moins parmi les zones entrée n est défini',
                                'status' => false,
                                'data' => null
                            ]
                        ); 
                    }
                
            }
        }

        //on recherche l'utilisateur'
        $user = User::find($request->id_user);
        $user->id_zone = json_encode($request->id_zone);

        //Envoie des information
        if($user->save()){

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['user' => $user]
                ]
            );

        }else{

            return response()->json(
                [
                    'message' => 'ecette zone n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }



    /**
     * modification d'une zone
     */
    public function update(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'map' => ['nullable', 'string'],
            'description' => ['nullable', 'string']
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
        $reference = $request->reference;
        $map = $request->map;
        $description = $request->description;

        // rechercher la zone
        $zone = Zone::find($id);

        // Modifier la zone
        $zone->nom = $name;
        $zone->reference = $reference;
        $zone->map = $map;
        $zone->description = $description;


        if ($zone->save()) {
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['zone' => $zone]
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
     * //lister les zone
     */
    public function list()
    {
        if (Zone::where('deleted_at', null)) {
            $zones = Zone::where('deleted_at', null)->get();
		
			$returenedZone = [];
            foreach($zones as $zone) 
			{
				$agents = [];
				$recouvreurs = [];
				
                $users = User::all(); 
				 foreach($users as $user) 
				 {
					 $userRole = $user->roles->pluck('name','name')->all();
					 if($userRole === Roles::AGENT) 
					 {
						  $user_zones = json_decode($user->id_zone);
						  if(array_search($zone->id, $user_zones)) $agents[] = $user;
					 }
					 else if($userRole === Roles::RECOUVREUR)
					 {
						 $user_zones = json_decode($user->id_zone);
						 if(array_search($zone->id, $user_zones)) $recouvreurs[] = $user;
					 }
				 }

                $returenedZone[] = ['zone' => $zone, 'agents' => count($agents), 'recouvreurs' => count($recouvreurs)];
            }    
			
			
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['zones' => $returenedZone]
                ]
            );
         }else{
            return response()->json(
                [
                    'message' => 'pas de zone à lister',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }

    /**
     * //supprimer une zone
     */
    public function destroy($id)
    {
        if (Zone::find($id)) {
            $zone = Zone::find($id);
            $zone->deleted_at = now();
            if ($zone->save()) {

                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => 'zone archivée',
                        'status' => true,
                        'data' => null
                    ]
                );
            } else {
                // Renvoyer une erreur
                return response()->json(
                    [
                        'message' => 'erreur lors de l archivage',
                        'status' => false,
                        'data' => null
                    ]
                );
            } 
         }else{
            return response()->json(
                [
                    'message' => 'cette zone n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }
}

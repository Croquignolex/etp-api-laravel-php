<?php

namespace App\Http\Controllers\API;


namespace App\Http\Controllers\API;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;
use App\Agent;
use App\Zone;
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
        $description = $request->description;


        // Nouvelle zone
        $zone = new Zone ([
            'nom' => $name,
            'reference' => $reference,
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


        //Envoie des information
        if(Zone::find($id)){

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['zone' => $zone]
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
            'id_zone' => ['required', 'Numeric']
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

        //on recherche l'utilisateur'
        $user = User::find($request->id_user);
        $user->id_zone = $request->id_zone;

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
        $description = $request->description;

        // rechercher la zone
        $zone = Zone::find($id);

        // Modifier la zone
        $zone->nom = $name;
        $zone->reference = $reference;
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
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['zones' => $zones]
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

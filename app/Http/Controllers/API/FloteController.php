<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Flote;
use Illuminate\Support\Facades\Validator;

class FloteController extends Controller
{

        /**

     * les conditions de lecture des methodes

     */

    function __construct()

    {

         $this->middleware('role:Admin'); 

    }


    /**
     * //Creer une flote.
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:255'],
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


        // Nouvel Flote
        $flote = new Flote([
            'nom' => $name,
            'reference' => $reference,
            'description' => $description
        ]);

        // creation de La flote
        if ($flote->save()) {

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Flote créée',
                    'status' => true,
                    'data' => ['flote' => $flote]
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
     * //details d'une flote'
     */
    public function show($id)
    {
        //on recherche la flote en question
        $Flote = Flote::find($id);


        //Envoie des information
        if(Flote::find($id)){

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['flote' => $Flote]
                ]
            );

        }else{

            return response()->json(
                [
                    'message' => 'ecette flote n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * modification d'une flote
     */
    public function update(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'name' => ['required', 'string', 'max:255'],
            'reference' => ['required', 'string', 'max:255'],
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

        // rechercher l'agent
        $flote = Flote::find($id);

        // Modifier la flote
        $flote->nom = $name;
        $flote->reference = $reference;
        $flote->description = $description;


        if ($flote->save()) {
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['flote' => $flote]
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
     * //lister les flotes
     */
    public function list()
    {
        if (Flote::where('deleted_at', null)) {
            $flote = Flote::where('deleted_at', null)->get();
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['flote' => $flote]
                ]
            );
         }else{
            return response()->json(
                [
                    'message' => 'pas de flote à lister',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }

    /**
     * //supprimer une flote
     */
    public function destroy($id)
    {
        if (Flote::find($id)) {
            $flote = Flote::find($id);
            $flote->deleted_at = now();
            if ($flote->save()) {

                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => 'Flote archivée',
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
                    'message' => 'cet agent n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }
}

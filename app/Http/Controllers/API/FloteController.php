<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Flote;
use Illuminate\Support\Facades\Validator;

class FloteController extends Controller
{


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
                    return response()->json(['error'=>$validator->errors(), 'status'=>401], 401);            
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
                    'flote' => $flote,
                    'success' => 'true', 
                    'status'=>200,
                ],
                200
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'erreur lors de la Creation',
                    'status'=>500,
                    'success' => 'false'
                ],
                500
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

            return response()->json(['success' => $Flote, 'status'=>200]);

        }else{

            return response()->json(['error' => 'cette flote n existe pas', 'status'=>204]);
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
                    return response()->json(['error'=>$validator->errors(), 'status'=>401], 401);            
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
                    'message' => 'Flote modifié',
                    'agent' => $flote, 
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
     * //lister les flotes
     */
    public function list()
    {
        if (Flote::where('deleted_at', null)) {
            $flote = Flote::where('deleted_at', null)->get();
            return response()->json(['success' => $flote, 'status'=>200]);
         }else{
            return response()->json(['error' => 'pas de flote à lister', 'status'=>204]); 
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
                        'agent' => $flote, 
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

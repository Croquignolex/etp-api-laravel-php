<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Flote;
use App\Puce;
use Illuminate\Support\Facades\Validator;

class FloteController extends Controller
{

        /**

     * les conditions de lecture des methodes

     */

    function __construct(){

        $this->middleware('permission:Superviseur');

    }
 

    /**
     * //Creer une flote.
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'name' => ['required', 'string', 'max:255'],
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
        $description = $request->description;


        // Nouvel Flote
        $flote = new Flote([
            'nom' => $name,
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
        $flote = Flote::find($id);
		  
		$puces = json_decode($flote->id_flote);
		$puces_list = [];
		
		if ($puces != null) {
			foreach ($puces as $puce) {
				$puces_list[] = Puce::Find($puce);
			}
		}
  
        //Envoie des information
        if(Flote::find($id)){

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['flote' => $flote, 'puces' => $puces_list]
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
        $description = $request->description;

        // rechercher la flote
        $flote = Flote::find($id);

        // Modifier la flote
        $flote->nom = $name;
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
            $flotes = Flote::where('deleted_at', null)->get();
			$returenedFlote = [];
            foreach($flotes as $flote) {

                $puces = json_decode($flote->id_flote);
                $puces_list = [];
                
                if ($puces != null) {
                    foreach ($puces as $puce) {
                        $puces_list[] = Puce::Find($puce);
                    }
                }

                $returenedFlote[] = ['flote' => $flote, 'puces' => $puces_list];
            }         
			
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['flotes' => $returenedFlote]
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
                    'message' => 'cet Flote n existe pas',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }
}

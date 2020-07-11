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
		  
        //Envoie des information
        if(Flote::find($id)){

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['flote' => $flote, 'puces' => $flote->puces]
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
                    'data' => ['flote' => $flote, 'puces' => $flote->puces]
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
     * ajouter une puce à une flotte
     */
    public function ajouter_puce(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
			'numero' => ['required', 'string', 'max:255', 'unique:puces,numero'],
            'reference' => ['nullable', 'string', 'max:255','unique:puces,reference'], 
            //'id_agent' => ['required', 'numeric'],
            'nom' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'numeric'],
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
		$nom = $request->nom;
        $type = $request->type;
        $numero = $request->numero;
		$id_agent = $request->id_agent;
        $reference = $request->reference;
        $description = $request->description;

        // rechercher la flote
        $flote = Flote::find($id);

        // ajout de mla nouvelle puce
        $puce = $flote->puces()->create([
            'nom' => $nom,
			'type' => $type,
			'numero' => $numero,
			'id_agent' => $id_agent,
            'reference' => $reference, 
            'description' => $description
		]);

        if ($puce !== null) {
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['flote' => $flote, 'puces' => $flote->puces]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => "Erreur l'ors de l'ajout de la nouvelle puce",
                    'status' => false,
                    'data' => null
                ]
            );
        } 
    }
	
	/**
     * ajouter une puce à une flotte
     */
    public function delete_puce(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [  
            'id_puce' => ['required', 'numeric']
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
		$id_puce = $request->id_puce;
          
        // rechercher la flote
        $flote = Flote::find($id); 
		$puce = Puce::find($id_puce);
        $puce->deleted_at = now();
		$puce->save();
		
        if ($puce !== null) {
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['flote' => $flote, 'puces' => $flote->puces]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => "Erreur l'ors de l'ajout de la nouvelle puce",
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
			
			$returenedFlotes = [];
			
            foreach($flotes as $flote) { 
                $returenedFlotes[] = ['flote' => $flote, 'puces' => $flote->puces->count()];
            }         
			
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['flotes' => $returenedFlotes]
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
				
				$flotes = Flote::where('deleted_at', null)->get();
			
				$returenedFlotes = [];
				
				foreach($flotes as $flote) { 
					$returenedFlotes[] = ['flote' => $flote, 'puces' => $flote->puces->count()];
				}         

                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => 'Flote archivée',
                        'status' => true,
                        'data' => ['flotes' => $returenedFlotes]
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

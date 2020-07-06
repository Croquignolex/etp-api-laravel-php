<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Puce;
use App\Flote;
use App\User;
use Illuminate\Support\Facades\Validator;

class PuceController extends Controller
{
    /**

     * les conditions de lecture des methodes

     */

     function __construct(){

        $this->middleware('permission:Superviseur');

    }


    /**
     * //Creer une puce.
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'numero' => ['required', 'string', 'max:255', 'unique:puces,numero'],
            'reference' => ['nullable', 'string', 'max:255','unique:puces,reference'],
            'id_flotte' => ['required', 'Numeric'],
            'id_agent' => ['required', 'Numeric'],
            'nom' => ['required', 'string'],
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
             
        $numero = $request->numero;
        $reference = $request->reference;
        $id_flotte = $request->id_flotte;
        $id_agent = $request->id_agent;
        $nom = $request->nom;
        $description = $request->description;



        // Nouvelle puce
        $puce = new Puce([
            'numero' => $numero,
            'nom' => $nom,
            'reference' => $reference,
            'id_flotte' => $id_flotte,
            'id_agent' => $id_agent,
            'description' => $description
        ]);

        // creation de La puce
        if ($puce->save()) {

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'puce créée',
                    'status' => true,
                    'data' => ['puce' => $puce]
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
     * //details d'une puce'
     */
    public function show($id)
    {
        //on recherche la puce en question
        $puce = Puce::find($id);

        //Envoie des information
        if(puce::find($id)){
			
			$user = User::find($puce->agent->id_user);
			
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['puce' => $puce, 'flote' => $puce->flote->nom, 'agent' => $user->name]
                ]
            );

        }else{

            return response()->json(
                [
                    'message' => "Cette puce n'existe pas",
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }

    /**
     * modification d'une puce
     */
    public function update(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'reference' => ['nullable', 'string', 'max:255', 'unique:puces,reference'],
            //'id_flotte' => ['required', 'Numeric'],
            //'id_agent' => ['required', 'Numeric'],
            'nom' => ['required', 'string'],
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
            
        $numero = $request->numero;
        $nom = $request->nom;
        $reference = $request->reference;
        //$id_flotte = $request->id_flotte;
        //$id_agent = $request->id_agent;
        $description = $request->description;

        // rechercher la puce
        $puce = Puce::find($id);

        // Modifier la puce
        $puce->numero = $numero;
        $puce->nom = $nom;
        $puce->reference = $reference;
        //$puce->id_flotte = $id_flotte;
        //$puce->id_agent = $id_agent;
        $puce->description = $description;


        if ($puce->save()) {
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['puce' => $puce]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'Erreur lors de la modification',
                    'status' => false,
                    'data' => null
                ]
            );
        } 
    }

    /**
     * modification de l'opérateur de la puce
     */
    public function update_flote(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'id_flotte' => ['required', 'Numeric']
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
        $id_flotte = $request->id_flotte;

        // rechercher la puce
        $puce = Puce::find($id);

        // Modifier la puce
        $puce->id_flotte = $id_flotte;

        if ($puce->save()) {
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['puce' => $puce]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'Erreur lors de la modification',
                    'status' => false,
                    'data' => null
                ]
            );
        } 
    }
	
	/**
     * modification de l'agent de la puce
     */
    public function update_agent(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'id_agent' => ['required', 'Numeric'],
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
        $id_agent = $request->id_agent;

        // rechercher la puce
        $puce = Puce::find($id);

        // Modifier la puce
        $puce->id_agent = $id_agent;

        if ($puce->save()) {
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['puce' => $puce]
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => 'Erreur lors de la modification',
                    'status' => false,
                    'data' => null
                ]
            );
        } 
    }
	
	/**
     * //lister les puces
     */
    public function list()
    {
        if (Puce::where('deleted_at', null)) {
            $puces = Puce::where('deleted_at', null)->get();
			
			$returenedPuces = [];
			
            foreach($puces as $puce) {
				$user = User::find($puce->agent->id_user); 
				//$flote = Flote::find($puce->id_flotte);
				//$nom = $flote->nom;  
                $returenedPuces[] = ['puce' => $puce, 'flote' => $puce->flote->nom, 'agent' => $user->name];
            } 
			
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['puces' => $returenedPuces]
                ]
            );
         }else{
            return response()->json(
                [
                    'message' => 'Pas de puce à lister',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }

    /**
     * //lister les puces
     */
    public function list_puce_agent($id)
    {
        $puce = Puce::where('id_agent', $id)->get();
        if ($puce->count() != 0) {
            
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['puces' => $puce]
                ]
            );
         }else{
            return response()->json(
                [
                    'message' => 'pas de puce à lister',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }

    /**
     * //lister les puces
     */
    public function list_puce_flotte($id)
    {
        $puce = Puce::where('id_flotte', $id)
        ->get();

        if ($puce->count() != 0) {
            $puce = Puce::where('id_flotte', $id)
            ->get();
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['puces' => $puce]
                ]
            );
         }else{
            return response()->json(
                [
                    'message' => 'pas de puce à lister',
                    'status' => false,
                    'data' => null
                ]
            );
         }
    }

    /**
     * //supprimer une puce
     */
    public function destroy($id)
    {
        if (Puce::find($id)) {
            $puce = puce::find($id);
            $puce->deleted_at = now();
            if ($puce->save()) {

                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => 'Puce archivée',
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

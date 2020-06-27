<?php

namespace App\Http\Controllers\API;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Puce;
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
            'reference' => ['nullable', 'string', 'max:255'],
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

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['puce' => $puce]
                ]
            );

        }else{

            return response()->json(
                [
                    'message' => 'ecette puce n existe pas',
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
            'reference' => ['nullable', 'string', 'max:255'],
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
        $nom = $request->nom;
        $reference = $request->reference;
        $id_flotte = $request->id_flotte;
        $id_agent = $request->id_agent;
        $description = $request->description;

        // rechercher la puce
        $puce = Puce::find($id);

        // Modifier la puce
        $puce->numero = $numero;
        $puce->nom = $nom;
        $puce->reference = $reference;
        $puce->id_flotte = $id_flotte;
        $puce->id_agent = $id_agent;
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
                    'message' => 'erreur lors de la modification',
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
            $puce = Puce::where('deleted_at', null)->get();
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

<?php

namespace App\Http\Controllers\API;

use App\Corporate;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Enums\Roles;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Corporate as CorporateResource;
use App\Http\Resources\Puce as PuceResource;
use Illuminate\Database\Eloquent\Collection;

class CorporateController extends Controller
{

    /**

     * les conditions de lecture des methodes

     */

    function __construct(){

        $superviseur = Roles::SUPERVISEUR;        
        $this->middleware("permission:$superviseur");
    } 

    /**
     * //Creer une corporate.
     */
    public function store(Request $request)
    {
        
        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'nom' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'responsable' => ['required', 'string'],            
            'dossier' => ['nullable', 'file', 'max:10000'],
            'adresse' => ['required', 'string'],
            'numeros_agents' => ['nullable', 'string', 'max:255'],
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
        
        $nom = $request->nom;
        $phone = $request->phone;
        $responsable = $request->responsable;
        $adresse = $request->adresse;
        $numeros_agents = $request->numeros_agents;
        $description = $request->description;
        
        $dossier = null;
        if ($request->hasFile('dossier') && $request->file('dossier')->isValid()) {
            $dossier = $request->dossier->store('files/dossier/corporate');
        }
        

        // Nouvelle corporate
        $corporate = new Corporate ([
            'nom' => $nom,
            'phone' => $phone,
            'responsable' => $responsable,            
            'dossier' => $dossier,
            'adresse' => $adresse,
            'numeros_agents' => $numeros_agents,
            'description' => $description,
            
        ]);       
       

        // creation de La corporate
        if ($corporate->save()) {

            // Renvoyer un message de succès
            return new CorporateResource($corporate);
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

     * details d'une corporate

     */

    public function show($id)

    {

        $corporate = Corporate::find($id);

        if (isset($corporate)) {

            return new CorporateResource($corporate);

        }else {

            return response()->json(
                [
                    'message' => "une erreur c'est produite",
                    'status' => false,
                    'data' => null
                ]
            );

        }       


    }


    /**
     * Modifier un Corporate
     */
    public function update(Request $request, $id)
    { 
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'nom' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:255'],
            'responsable' => ['required', 'string'],
            'adresse' => ['required', 'string'],
            'numeros_agents' => ['nullable', 'string', 'max:255'],
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
        $nom = $request->nom;
        $phone = $request->phone;
        $responsable = $request->responsable;      
        $adresse = $request->adresse; 
        $numeros_agents = $request->numeros_agents; 
        $description = $request->description;
         
        // rechercher la Corporate
        $corporate = Corporate::find($id);
        
        // Modifier la corporate
		$corporate->nom = $nom;
		$corporate->phone = $phone;
		$corporate->responsable = $responsable;
        $corporate->adresse = $adresse;
        $corporate->numeros_agents = $numeros_agents;
        $corporate->description = $description;
 
        if ($corporate->save()) {

			return new CorporateResource($corporate);
            
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
     * ////lister les corporates
     */
    public function list()
    {
        return CorporateResource::collection(Corporate::all());
        
    }



    /**
     * ////supprimer une corporate
     */
    public function destroy($id)
    {
        if (Corporate::find($id)) {
            Corporate::destroy($id);
            return response()->json(
                [
                    'message' => "corporate supprimé",
                    'status' => true,
                    'data' => null
                ]
            );
        }
        return response()->json(
            [
                'message' => "erreur lors de la suppression",
                'status' => true,
                'data' => null
            ]
        );
    }

    /**
     * ////supprimer une corporate
     */
    public function edit_folder(Request $request, $id)
    {

        // Valider données envoyées
        $validator = Validator::make($request->all(), [          
            'dossier' => ['required', 'file', 'max:10000']
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


        if ($corporate = Corporate::find($id)) {

            // on recupère le dossier passé en paramettre
            $dossier = null;
            if ($request->hasFile('dossier') && $request->file('dossier')->isValid()) {
                $dossier = $request->dossier->store('files/dossier/corporate');
            }
            $corporate->dossier = $dossier;

            if ($corporate->save()) {
                
                return new CorporateResource($corporate);
            }
            
        }
        return response()->json(
            [
                'message' => "erreur lors de la modification",
                'status' => true,
                'data' => null
            ]
        );
    }


    /**
     * ////lister les puces d'une corporates
     */
    public function list_puces($id)
    {
        if ($corporate = Corporate::find($id)) {

            // on recupère les puces
            $puces = $corporate->puces;

            return PuceResource::collection($puces);
            
        }
        return response()->json(
            [
                'message' => "une erreure c'est produite",
                'status' => true,
                'data' => null
            ]
        );
    }

}

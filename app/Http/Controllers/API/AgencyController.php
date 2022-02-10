<?php

namespace App\Http\Controllers\API;

use App\Puce;
use App\Agency;
use App\Type_puce;
use App\Enums\Roles;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class AgencyController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $agent = Roles::AGENT;
        $comptable = Roles::COMPATBLE;
        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $controlleur = Roles::CONTROLLEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$recouvreur|$superviseur|$ges_flotte|$agent|$controlleur|$comptable");
    }

    /**
     * Creer une agency.
     */
    // SUPERVISEUR
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'manager' => ['nullable', 'Numeric'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // Récupérer les données validées
        $name = $request->name;
        $description = $request->description;

        // Nouvelle zone
        $agency = new Agency ([
            'name' => $name,
            'description' => $description
        ]);
        $agency->save();

        // Renvoyer un message de succès
        return response()->json([
            'message' => 'Agence créer avec succès',
            'status' => true,
            'data' => [
                'agency' => $agency,
                'puces' => $agency->puces,
                'manager' => $agency->manager,
            ]
        ]);
    }

    /**
     * //details d'une agency'
     */
    public function show($id)
    {
        //on recherche la agency en question
        $agency = Agency::find($id);

        if($agency === null) {
            return response()->json([
                'message' => "Cet agence n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'agency' => $agency,
                'puces' => $agency->puces,
                'manager' => $agency->manager,
            ]
        ]);
    }

    /**
     * modification d'une agency
     */
    public function update(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string']
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // Récupérer les données validées

        $name = $request->name;
        $description = $request->description;

        // rechercher la zone
        $agency = Agency::find($id);

        // Modifier la zone
        $agency->name = $name;
        $agency->description = $description;

        $agency->save();

        // Renvoyer un message de succès
        return response()->json([
            'message' => 'Agence mise à jour avec succès',
            'status' => true,
            'data' => [
                'agency' => $agency,
                'puces' => $agency->puces,
                'manager' => $agency->manager,
            ]
        ]);
    }

    /**
     * lister les agencies
     */
    // SUPERVISOR
    public function list()
    {
        $agencies = Agency::orderBy('created_at', 'desc')->paginate(9);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'agencies' => $this->agenciesResponse($agencies->items()),
                'hasMoreData' => $agencies->hasMorePages(),
            ]
        ]);
    }

    /**
     * //lister toutes les agencies
     */
    //...
    // RESOURCE
    public function list_all()
    {
        $agencies = Agency::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'agencies' => $this->agenciesResponse($agencies)
            ]
        ]);
    }

    /**
     * ajouter une puce à une agence
     */
    public function ajouter_puce(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'numero' => ['required', 'string', 'max:255', 'unique:puces,numero'],
            'reference' => ['nullable', 'string', 'max:255'],
            'id_flotte' => ['required', 'numeric'],
            'nom' => ['required', 'string'],
            'description' => ['nullable', 'string']
        ]);

        if(Puce::where('numero', $request->numero)->first()) {
            return response()->json([
                'message' => "Ce compte existe déjà dans le système",
                'status' => false,
                'data' => null
            ]);
        }

        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // Récupérer les données validées
        $reference = $request->reference;

        $nom = $request->nom;
        $type = Type_puce::where('name', $reference)->first()->id;
        $numero = $request->numero;
        $id_flotte = $request->id_flotte;
        $reference = $request->reference;
        $description = $request->description;

        // rechercher la flote
        $agency = Agency::find($id);

        // ajout de mla nouvelle puce
        $puce = $agency->puces()->create([
            'nom' => $nom,
            'numero' => $numero,
            'reference' => $reference,
            'id_flotte' => $id_flotte,
            'description' => $description,
            'type' => $type,
        ]);

        if ($puce !== null) {
            // Renvoyer un message de succès
            return response()->json([
                'message' => 'Puce ajoutée avec succès',
                'status' => true,
                'data' => [
                    'agency' => $agency,
                    'puces' => $agency->puces,
                    'manager' => $agency->manager,
                ]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => "Erreur l'ors de l'ajout de la nouvelle puce",
                'status' => false,
                'data' => null
            ]);
        }
    }

    // Build agencies return data
    private function agenciesResponse($agencies)
    {
        $returnedAgencies = [];

        foreach($agencies as $agency)
        {
            $returnedAgencies[] = [
                'agency' => $agency,
                'manager' => $agency->manager
            ];
        }

        return $returnedAgencies;
    }
}

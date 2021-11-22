<?php

namespace App\Http\Controllers\API;

use App\Agency;
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

        // Nouvelle zone
        $vendor = new Agency ([
            'name' => $name,
            'description' => $description
        ]);
        $vendor->save();

        // Renvoyer un message de succès
        return response()->json([
            'message' => 'Agence créer avec succès',
            'status' => true,
            'data' => [
                'agency' => $vendor
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
                'agency' => $agency
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
            'data' => ['agency' => $agency]
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
    public function list_all()
    {
        $agencies = Agency::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'vendors' => $this->agenciesResponse($agencies)
            ]
        ]);
    }

    // Build agencies return data
    private function agenciesResponse($agencies)
    {
        $returnedAgencies = [];

        foreach($agencies as $agency)
        {
            $returnedAgencies[] = ['agency' => $agency];
        }

        return $returnedAgencies;
    }
}

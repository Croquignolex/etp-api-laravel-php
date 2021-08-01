<?php

namespace App\Http\Controllers\API;

use App\Vendor;
use App\Enums\Roles;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class VendorController extends Controller
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
     * Creer une vendor.
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
        $vendor = new Vendor ([
            'solde' => 0,
            'name' => $name,
            'description' => $description
        ]);
        $vendor->save();

        // Renvoyer un message de succès
        return response()->json([
            'message' => 'Fournisseur créer avec succès',
            'status' => true,
            'data' => [
                'vendor' => $vendor
            ]
        ]);
    }

    /**
     * //details d'une vendor'
     */
    public function show($id)
    {
        //on recherche la zone en question
        $vendor = Vendor::find($id);

        //Envoie des information
        if($vendor !== null){

            return response()->json([
                'message' => '',
                'status' => true,
                'data' => [
                    'vendor' => $vendor
                ]
            ]);
        } else {
            return response()->json([
                'message' => "Ce fournisseur n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * modification d'une vendor
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
        $vendor = Vendor::find($id);

        // Modifier la zone
        $vendor->name = $name;
        $vendor->description = $description;

        if ($vendor->save()) {
            // Renvoyer un message de succès
            return response()->json([
                'message' => 'Founisseur mise à jour avec succès',
                'status' => true,
                'data' => ['vendor' => $vendor]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de la modification',
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * lister les vendors
     */
    // SUPERVISOR
    public function list()
    {
        $vendors = Vendor::orderBy('created_at', 'desc')->paginate(9);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'vendors' => $this->vendorsResponse($vendors->items()),
                'hasMoreData' => $vendors->hasMorePages(),
            ]
        ]);
    }

    /**
     * //lister toutes les vendors
     */
    public function list_all()
    {
        $vendors = Vendor::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'vendors' => $this->vendorsResponse($vendors)
            ]
        ]);
    }

    // Build vendors return data
    private function vendorsResponse($vendors)
    {
        $returnedVendors = [];

        foreach($vendors as $vendor)
        {
            $returnedVendors[] = ['vendor' => $vendor];
        }

        return $returnedVendors;
    }
}

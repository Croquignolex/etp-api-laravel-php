<?php

namespace App\Http\Controllers\API;

use App\Puce;
use App\Type_puce;
use App\Corporate;
use App\Enums\Roles;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\Puce as PuceResource;
use App\Http\Resources\Corporate as CorporateResource;

class CorporateController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $comptable = Roles::COMPATBLE;
        $superviseur = Roles::SUPERVISEUR;
        $controlleur = Roles::CONTROLLEUR;
        $this->middleware("permission:$superviseur|$controlleur|$comptable");
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
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
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
            return response()->json([
                'message' => 'Entreprise crée avec succès',
                'status' => true,
                'data' => [
                    'entreprise' => $corporate
                ]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de la Creation',
                'status' => false,
                'data' => null
            ]);
        }
    }

     /**
     * details d'une corporate
     */
    public function show($id)
    {
        $corporate = Corporate::find($id);

        if (isset($corporate)) {
        return response()->json([
                'message' => '',
                'status' => true,
                'data' => [
                    'entreprise' => new CorporateResource($corporate)
                ]
            ]);
        } else {
            return response()->json([
                'message' => "Une erreur c'est produite",
                'status' => false,
                'data' => null
            ]);
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
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
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
            return response()->json([
                'message' => "Informations de l'entreprise mises à jour avec succès",
                'status' => true,
                'data' => [
                    'entreprise' => new CorporateResource($corporate)
                ]
            ]);
        } else {
            // Renvoyer une erreur
            return response()->json([
                'message' => 'Erreur lors de la modification',
                'status'=>false,
                'data' => null
            ]);
        }

    }

    /**
     * ////lister les corporates
     */
    public function list()
    {
        $corporates = Corporate::orderBy('created_at', 'desc')->paginate(12);

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'entreprises' => CorporateResource::collection($corporates->items()),
                'hasMoreData' => $corporates->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister toutes les corporates
     */
    // SUPERVISOR
    public function list_all()
    {
        $corporates = Corporate::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'entreprises' => CorporateResource::collection($corporates)
            ]
        ]);
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
                    'message' => 'corporate supprimé',
                    'status' => true,
                    'data' => CorporateResource::collection(Corporate::all())
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
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        if ($corporate = Corporate::find($id)) {

            // on recupère le dossier passé en paramettre
            $dossier = null;
            if ($request->hasFile('dossier') && $request->file('dossier')->isValid()) {
                $dossier = $request->dossier->store('files/dossier/corporate');
            }
            $corporate->dossier = $dossier;

            if ($corporate->save()) {
                return response()->json([
                    'message' => 'Document entreprise mis à jour avec succès',
                    'status' => true,
                    'data' => [
                        'entreprise' => new CorporateResource($corporate)
                    ]
                ]);
            }
        }
        return response()->json([
            'message' => "Erreur lors de la modification",
            'status' => true,
            'data' => null
        ]);
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

    /**
     * ajouter une puce à une entreprise
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
        $corporate = Corporate::find($id);

        // ajout de mla nouvelle puce
        $puce = $corporate->puces()->create([
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
                'data' =>  [
                    'entreprise' => new CorporateResource($corporate)
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

    /**
     * ajouter une puce à une entreprise
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
                    'message' => "Le formulaire contient des champs mal renseignés",
                    'status' => false,
                    'data' => null
                ]
            );
        }

        // Récupérer les données validées
        $id_puce = $request->id_puce;

        // rechercher l'entreprise
        $corporate = Corporate::find($id);
        $puce = Puce::find($id_puce);
        $puce->deleted_at = now();
        $puce->save();

        if ($puce !== null) {
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => new CorporateResource($corporate)
                ]
            );
        } else {
            // Renvoyer une erreur
            return response()->json(
                [
                    'message' => "Erreur l'ors de la suppression d'une puce",
                    'status' => false,
                    'data' => null
                ]
            );
        }
    }
}

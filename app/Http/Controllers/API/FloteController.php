<?php

namespace App\Http\Controllers\API;

use App\Puce;
use App\Flote;
use App\Enums\Roles;
use App\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Validator;

class FloteController extends Controller
{
	/**
     * les conditions de lecture des methodes
     */
    function __construct()
    {
        $recouvreur = Roles::RECOUVREUR;
        $superviseur = Roles::SUPERVISEUR;
        $ges_flotte = Roles::GESTION_FLOTTE;
        $this->middleware("permission:$superviseur|$ges_flotte|$recouvreur");
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
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
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
        if ($flote->save())
        {
            // Renvoyer un message de succès
            return response()->json([
                'message' => 'Opérateur crée avec succès',
                'status' => true,
                'data' => [
                    'flote' => $flote
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
     * //details d'une flote'
     */
    public function show($id)
    {
        //on recherche la flote en question
        $flote = Flote::find($id);

        //Envoie des information
        if(Flote::find($id))
        {
            return response()->json([
                'message' => '',
                'status' => true,
                'data' => [
                    'flote' => $flote,
                    'puces' => $flote->puces
                ]
            ]);
        } else {
            return response()->json([
                'message' => "Cet opérateur n'existe pas",
                'status' => false,
                'data' => null
            ]);
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
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
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
            return response()->json([
                'message' => 'Opérateur mis à jour avec succès',
                'status' => true,
                'data' => [
                    'flote' => $flote,
                    'puces' => $flote->puces
                ]
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
     * ajouter une puce à une flotte
     */
    public function ajouter_puce(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
			'numero' => ['required', 'string', 'max:255', 'unique:puces,numero'],
            'reference' => ['nullable', 'string', 'max:255'],
            'id_agent' => ['nullable', 'numeric'],
            'id_corporate' => ['nullable', 'numeric'],
            'id_rz' => ['nullable', 'numeric'],
            'id_ressource' => ['nullable', 'numeric'],
            'nom' => ['required', 'string'],
            'description' => ['nullable', 'string'],
            'type' => ['required', 'numeric'],
        ]);

        if(Puce::where('numero', $request->numero)->get()) {
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
		$nom = $request->nom;
        $type = $request->type;
        $id_rz = $request->id_rz;
        $numero = $request->numero;
        $reference = $request->reference;
        $corporate = $request->id_corporate;
        $description = $request->description;

        $id_agent = $reference === Roles::AGENT ? $request->id_agent : $request->id_ressource;
        $user = User::find($id_agent);
        $agent = $user === null ? null : $user->agent->first()->id;

        // rechercher la flote
        $flote = Flote::find($id);

        // ajout de mla nouvelle puce
        $puce = $flote->puces()->create([
            'nom' => $nom,
			'type' => $type,
            'id_rz' => $id_rz,
            'numero' => $numero,
            'id_agent' => $agent,
            'reference' => $reference,
            'corporate' => $corporate,
            'description' => $description,
        ]);

        if ($puce !== null) {
            // Renvoyer un message de succès
            return response()->json([
                'message' => 'Puce ajoutée avec succès',
                'status' => true,
                'data' => ['flote' => $flote, 'puces' => $flote->puces]
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
                    'message' => "Le formulaire contient des champs mal renseignés",
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
                    'message' => "Erreur l'ors de la suppression d'une puce",
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
        $flotes = Flote::orderBy('created_at', 'desc')->paginate(12);

        $operators_response =  $this->operatorsResponse($flotes->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'flotes' => $operators_response,
                'hasMoreData' => $flotes->hasMorePages(),
            ]
        ]);
    }

    /**
     * Lister toutes les flotes
     */
    // SUPERVISOR
    public function list_all()
    {
        $flotes = Flote::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'flotes' => $this->operatorsResponse($flotes)
            ]
        ]);
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
					$returenedFlotes[] = ['flote' => $flote, 'puces' => $flote->puces];
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

    // Build operators return data
    private function operatorsResponse($operators)
    {
        $returnedOperators = [];

        foreach($operators as $flote) {
            $returnedOperators[] = ['flote' => $flote];
        }

        return $returnedOperators;
    }
}

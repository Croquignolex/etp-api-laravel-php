<?php

namespace App\Http\Controllers\API;

use App\Puce;
use App\User;
use App\Type_puce;
use App\Enums\Roles;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PuceController extends Controller
{
    /**
     * les conditions de lecture des methodes
     */
     function __construct(){
         $agent = Roles::AGENT;
         $responsable = Roles::RECOUVREUR;
         $superviseur = Roles::SUPERVISEUR;
         $ges_flotte = Roles::GESTION_FLOTTE;
         $this->middleware("permission:$superviseur|$ges_flotte|$responsable|$agent");
    }

    /**
     * //Creer une puce.
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'numero' => ['required', 'string', 'max:255', 'unique:puces,numero'],
            'reference' => ['nullable', 'string', 'max:255','unique:puces,reference'],
            'id_flotte' => ['required', 'Numeric'],
            //'id_agent' => ['required', 'Numeric'],
            //'id_corporate' => ['required', 'Numeric'],
            //'id_recouvreur' => ['required', 'Numeric'],
            'nom' => ['required', 'string'],
            'description' => ['nullable', 'string'],
			'type' => ['required', 'numeric'],
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
        $nom = $request->nom;
		$type = $request->type;
        $numero = $request->numero;

        $user = User::find($request->id_agent);
        $agent = $user->agent->first();

        $id_agent = $agent->id;

        $id_corporate = $request->id_corporate;
        $id_recouvreur = $request->id_recouvreur;
        $reference = $request->reference;
        $id_flotte = $request->id_flotte;
        $description = $request->description;

        // Nouvelle puce
        $puce = new Puce([
            'nom' => $nom,
			'type' => $type,
            'numero' => $numero,
            'id_agent' => $id_agent,
            'reference' => $reference,
            'id_flotte' => $id_flotte,
            'corporate' => $id_corporate,
            'description' => $description,
            'id_rz' => $id_recouvreur,
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
     * @param $id
     * @return JsonResponse
     */
    public function show($id)
    {
        //on recherche la puce en question
        $puce = Puce::find($id);

        //Envoie des information
        if(Puce::find($id)) {
			$id_agent = $puce->id_agent;
			$agent = is_null($id_agent) ? $id_agent : $puce->agent;
			$user = is_null($id_agent) ? $id_agent : User::find($puce->agent->id_user);
            return response()->json([
                'message' => '',
                'status' => true,
                'data' => [
                    'puce' => $puce,
                    'flote' => $puce->flote,
                    'type' => $puce->type_puce,
                    'agent' => $agent,
                    'user' => $user,
                    'corporate' => $puce->company,
                    'recouvreur' => $puce->rz,
                ]
            ]);
        } else {
            return response()->json([
                'message' => "Cette puce n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }
    }

    /**
     * modification d'une puce
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function update(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            //'reference' => ['nullable', 'string', 'max:255', 'unique:puces,reference'],
            'reference' => ['nullable', 'string', 'max:255'],
            //'id_flotte' => ['required', 'Numeric'],
            //'id_agent' => ['required', 'Numeric'],
            'nom' => ['required', 'string'],
            'description' => ['nullable', 'string']
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

        //$numero = $request->numero;
        $nom = $request->nom;
        $reference = $request->reference;
        //$id_flotte = $request->id_flotte;
        //$id_agent = $request->id_agent;
        $description = $request->description;

        // rechercher la puce
        $puce = Puce::find($id);

        // Modifier la puce
        //$puce->numero = $numero;
        $puce->nom = $nom;
        $puce->reference = $reference;
        //$puce->id_flotte = $id_flotte;
        //$puce->id_agent = $id_agent;
        $puce->description = $description;


        if ($puce->save()) {
			$id_agent = $puce->id_agent;
			$agent = is_null($id_agent) ? $id_agent : $puce->agent;
			$user = is_null($id_agent) ? $id_agent : User::find($puce->agent->id_user);
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'puce' => $puce,
                        'flote' => $puce->flote,
                        'type' => $puce->type_puce,
                        'agent' => $agent,
                        'user' => $user,
                        'corporate' => $puce->company,
                        'recouvreur' => $puce->rz,
                    ]
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
     * @param Request $request
     * @param $id
     * @return JsonResponse
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
                    'message' => "Le formulaire contient des champs mal renseignés",
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
			$id_agent = $puce->id_agent;
			$agent = is_null($id_agent) ? $id_agent : $puce->agent;
			$user = is_null($id_agent) ? $id_agent : User::find($puce->agent->id_user);
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'puce' => $puce,
                        'flote' => $puce->flote,
                        'type' => $puce->type_puce,
                        'agent' => $agent,
                        'user' => $user,
                        'corporate' => $puce->company,
                        'recouvreur' => $puce->rz,
                    ]
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
     * @param Request $request
     * @param $id
     * @return JsonResponse
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
                    'message' => "Le formulaire contient des champs mal renseignés",
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
			$id_agent = $puce->id_agent;
			$agent = is_null($id_agent) ? $id_agent : $puce->agent;
			$user = is_null($id_agent) ? $id_agent : User::find($puce->agent->id_user);
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'puce' => $puce,
                        'flote' => $puce->flote,
                        'type' => $puce->type_puce,
                        'agent' => $agent,
                        'user' => $user,
                        'corporate' => $puce->company,
                        'recouvreur' => $puce->rz,
                    ]
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
     * modification de l'entreprise de la puce
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function update_corporate(Request $request, $id)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_corporate' => ['required', 'Numeric'],
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
        $corporate = $request->id_corporate;

        // rechercher la puce
        $puce = Puce::find($id);

        // Modifier la puce
        $puce->corporate = $corporate;

        if ($puce->save()) {
            $id_agent = $puce->id_agent;
            $agent = is_null($id_agent) ? $id_agent : $puce->agent;
            $user = is_null($id_agent) ? $id_agent : User::find($puce->agent->id_user);
            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => [
                        'puce' => $puce,
                        'flote' => $puce->flote,
                        'type' => $puce->type_puce,
                        'agent' => $agent,
                        'user' => $user,
                        'corporate' => $puce->company,
                        'recouvreur' => $puce->rz,
                    ]
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
        $puces = Puce::orderBy('created_at', 'desc')->paginate(6);

        $sims_response =  $this->simsResponse($puces->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $sims_response,
                'hasMoreData' => $puces->hasMorePages(),
            ]
        ]);
    }

    /**
     * //lister les puces d'un reesponsable de zone
     */
    public function list_responsable()
    {
        $user = Auth::user();
        $userRole = $user->roles->first()->name;

        if($userRole === Roles::RECOUVREUR) {
            $puces = Puce::where('id_rz', $user->id)->orderBy('created_at', 'desc')->paginate(6);

            $sims_response =  $this->simsResponse($puces->items());

            return response()->json([
                'message' => '',
                'status' => true,
                'data' => [
                    'puces' => $sims_response,
                    'hasMoreData' => $puces->hasMorePages(),
                ]
            ]);
        } else {
            return response()->json([
                'message' => "Cet utilisateur n'est pas un responsable de zone",
                'status' => false,
                'data' => null
            ]);
        }

    }

    /**
     * //lister les puces d'un agent
     */
    public function list_agent()
    {
        $user = Auth::user();
        $agent = $user->agent->first();
        $userRole = $user->roles->first()->name;

        if($userRole === Roles::AGENT || $userRole === Roles::RESSOURCE) {
            $puces = Puce::where('id_agent', $agent->id)->orderBy('created_at', 'desc')->paginate(6);

            $sims_response =  $this->simsResponse($puces->items());

            return response()->json([
                'message' => '',
                'status' => true,
                'data' => [
                    'puces' => $sims_response,
                    'hasMoreData' => $puces->hasMorePages(),
                ]
            ]);
        } else {
            return response()->json([
                'message' => "Cet utilisateur n'est pas un agent/ressource",
                'status' => false,
                'data' => null
            ]);
        }

    }

    /**
     * //lister toutes les $sims_response
     */
    public function list_all()
    {
        $puces = Puce::orderBy('created_at', 'desc')->get();

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'puces' => $this->simsResponse($puces)
            ]
        ]);
    }

    /**
     * //lister les puces
     * @param $id
     * @return JsonResponse
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
     * @param $id
     * @return JsonResponse
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
     * @param $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        if (Puce::find($id)) {
            $puce = puce::find($id);
            $puce->deleted_at = now();
            if ($puce->save()) {
				$puces = Puce::where('deleted_at', null)->get();

				$returenedPuces = [];

				foreach($puces as $puce) {
					$id_agent = $puce->id_agent;
					$agent = is_null($id_agent) ? $id_agent : $puce->agent;
					$user = is_null($id_agent) ? $id_agent : User::find($puce->agent->id_user);
					//$flote = Flote::find($puce->id_flotte);
					//$nom = $flote->nom;
					$returenedPuces[] = [
					    'puce' => $puce,
                        'flote' => $puce->flote,
                        'type' => $puce->type_puce,
                        'agent' => $agent,
                        'user' => $user,
                        'recouvreur' => $puce->rz,
                    ];
				}
                // Renvoyer un message de succès
                return response()->json(
                    [
                        'message' => 'Puce archivée',
                        'status' => true,
                        'data' => ['puces' => $returenedPuces]
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

	/**
     * Liste des types de puces
     */
    public function types_puces_list()
    {
        //recuperation des roles
        $types = Type_puce::all();

        if ($types != null) {
            return response()->json([
                'message' => '',
                'status' => true,
                'data' => ['types' => $types]
            ]);
         } else{
            return response()->json([
                'message' => 'Aucun type de puce trouve',
                'status' => false,
                'data' => null
            ]);
         }
    }

    // Build sims return data
    private function simsResponse($sims)
    {
        $returenedPuces = [];

        foreach($sims as $puce) {

            $id_agent = $puce->id_agent;
            $agent = is_null($id_agent) ? $id_agent : $puce->agent;
            $user = is_null($id_agent) ? $id_agent : User::find($puce->agent->id_user);

            $returenedPuces[] = [
                'puce' => $puce,
                'user' => $user,
                'agent' => $agent,
                'flote' => $puce->flote,
                'corporate' => $puce->company,
                'recouvreur' => $puce->rz,
                'type' => $puce->type_puce,
            ];
        }

        return $returenedPuces;
    }
}

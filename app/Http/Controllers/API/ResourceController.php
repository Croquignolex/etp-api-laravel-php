<?php

namespace App\Http\Controllers\API;

use App\Agency;
use App\User;
use App\Agent;
use App\Caisse;
use App\Enums\Roles;
use App\Enums\Statut;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class ResourceController extends Controller
{
    /**
     * creer un Resource
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        // Valider données envoyées
        $validator = Validator::make($request->all(), [

            //user informations
            'name' => 'required',
            'phone' => 'required|numeric',
            'adresse' => 'nullable',
            'description' => 'nullable',
            'email' => 'nullable|string',
            'id_agency' => ['nullable', 'Numeric'],

            //Agent informations
            'base_64_image' => 'nullable',
            'base_64_image_back' => 'nullable',
            'document' => 'nullable|file|max:10000'
        ]);



        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        if (isset($request->phone)) {
            // on verifie si la zone est définie
            if (User::where('phone', $request->phone)->first()) {
                return response()->json([
                    'message' => "Numéro de téléphone déjà existant",
                    'status' => false,
                    'data' => null
                ]);
            }
        }

        if (isset($request->id_agency)) {
            // on verifie si la agency est définie
            if (!Agency::find($request->id_agency)) {
                return response()->json([
                    'message' => "La zone n'est pas definie",
                    'status' => false,
                    'data' => null
                ]);
            }
        }

        // Récupérer les données validées
        // users
        $name = $request->name;
        $phone = $request->phone;
        $adresse = $request->adresse;
        $description = $request->description;
        $email = $request->email;
        $id_agency = $request->id_agency;

        $role = Role::where('name', Roles::AGENT)->first();

        // Agent

        $dossier = null;
        if ($request->hasFile('document') && $request->file('document')->isValid()) {
            $dossier = $request->document->store('files/dossier/agents');
        }

        $img_cni = null;
        if ($request->hasFile('base_64_image') && $request->file('base_64_image')->isValid()) {
            $img_cni = $request->base_64_image->store('files/CNI_avant/agents');
        }

        $img_cni_back = null;
        if ($request->hasFile('base_64_image_back') && $request->file('base_64_image_back')->isValid()) {
            $img_cni_back = $request->base_64_image_back->store('files/CNI_arriere/agents');
        }

        //l'utilisateur connecté
        $add_by_id = Auth::user()->id;

        // Nouvel utilisateur
        $user = new User([
            'add_by' => $add_by_id,
            'avatar' => null,
            'name' => $name,
            'email' => $email,
            'password' => bcrypt("000000"),
            'phone' => $phone,
            'statut' => Statut::APPROUVE,
            'adresse' => $adresse,
            'id_agency' => $id_agency,
            'description' => $description
        ]);

        if ($user->save()) {

            //On crée la caisse de l'utilisateur
            $caisse = new Caisse([
                'nom' => 'Caisse ' . $request->name,
                'id_user' => $user->id,
                'solde' => 0
            ]);
            $caisse->save();

            $user->assignRole($role);

            //info user à renvoyer
            $success['token'] =  $user->createToken('MyApp')-> accessToken;
            $success['user'] =  $user;

            $user->setting()->create([
                'bars' => '[0,1,2,3,4,5,6,7,8,9]',
                'charts' => '[0,1,2,3,4,5,6,7,8,9]',
                'cards' => '[0,1,2,3,4,5,6,7,8,9]',
            ]);

            // Nouvel Agent
            $agent = new Agent([
                'id_creator' => $add_by_id,
                'id_user' => $user->id,
                'img_cni' => $img_cni,
                'dossier' => $dossier,
                'img_cni_back' => $img_cni_back,
                'reference' => Statut::RESOURCE,
                'ville' => "Douala",
                'pays' => "CAMAEROUN"
            ]);

            if ($agent->save()) {

                // Renvoyer un message de succès
                return response()->json([
                    'message' => 'Ressource crée avec succès',
                    'status' => true,
                    'data' => [
                        'agency' => $user->agency,
                        'user' => $user->setHidden(['deleted_at', 'add_by', 'id_agency']),
                        'agent' => $agent,
                        'caisse' => Caisse::where('id_user', $user->id)->first(),
                        'createur' => User::find($user->add_by)
                    ]
                ]);

            } else {
                // Renvoyer une erreur

                return response()->json([
                    'message' => "Erreur l'ors de la creation de l'agent",
                    'status' => false,
                    'data' => null
                ]);
            }
        } else {
            // Renvoyer un message de erreur
            return response()->json([
                'message' => "Erreur l'ors de la creation de l'agent",
                'status' => false,
                'data' => null
            ]);
        }

    }

    /**
     * liste des Resources
     *
     * @return JsonResponse
     */
    public function list()
    {
        $agents = Agent::where('reference', Statut::RESOURCE)
            ->orderBy('created_at', 'desc')
            ->paginate(6);

        $agents_response =  $this->agentsResponse($agents->items());

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'agents' => $agents_response,
                'hasMoreData' => $agents->hasMorePages(),
            ]
        ]);
    }

    /**
     * details d'un Resource
     * @param $id
     * @return JsonResponse
     */
    public function show($id)
    {
        //on recherche l'agent en question
        $user = User::find($id);
        $agent = $user->agent->first();

        //Envoie des information
        if($agent != null){
            $puces = is_null($agent) ? [] : $agent->puces;
            return response()->json([
                'message' => '',
                'status' => true,
                'data' => [
                    'agency' => $user->agency,
                    'user' => $user->setHidden(['deleted_at', 'add_by', 'id_agency']),
                    'agent' => $agent,
                    'createur' => User::find($user->add_by),
                    'caisse' => Caisse::where('id_user', $user->id)->first()
                ],
            ]);
        } else {
            return response()->json([
                'message' => "Cet ressource n'existe pas",
                'status' => false,
                'data' => null
            ]);
        }

    }

    /**
     * modification la agency de l'agent
     * @param Request $request
     * @param $id
     * @return JsonResponse
     */
    public function edit_agency_agent(Request $request, $id)
    {
        //voir si l'utilisateur à modifier existe
        if(!User::find($id)){
            // Renvoyer un message de notification
            return response()->json([
                'message' => 'Resource non trouvé',
                'status' => false,
                'data' => null
            ]);
        }

        // Valider données envoyées
        $validator = Validator::make($request->all(), [
            'id_agency' => ['required']
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        // on verifie si la zone est définit
        $agencyExist = Agency::find($request->input('id_agency'));
        if ($agencyExist === null) {
            return response()->json([
                'message' => "Cette agence n'est pas défini",
                'status' => false,
                'data' => null
            ]);
        }

        $user = User::find($id);
        $agent = $user->agent->first();
        $user->id_agency = $request->input('id_agency');

        if ($user->save()) {

            // Renvoyer un message de succès
            return response()->json(
                [
                    'message' => 'Agency mise à jour avec succès',
                    'status' => true,
                    'data' => [
                        'user' => $user->setHidden(['deleted_at', 'add_by', 'id_agency']),
                        'agency' => $user->agency,
                        'agent' => $agent,
                        'createur' => User::find($user->add_by),
                        'caisse' => Caisse::where('id_user', $user->id)->first()
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

    // Build agents return data
    private function agentsResponse($agents)
    {
        $returenedAgents = [];

        foreach($agents as $agent) {

            $user = User::find($agent->id_user);

            $returenedAgents[] = [
                'agency' => $user->agency,
                'user' => $user->setHidden(['deleted_at', 'add_by', 'id_agency']),
                'agent' => $agent,
                'caisse' => Caisse::where('id_user', $user->id)->first(),
                'createur' => User::find($user->add_by)
            ];
        }

        return $returenedAgents;
    }
}

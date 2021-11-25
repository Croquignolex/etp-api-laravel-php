<?php

namespace App\Http\Controllers\API;

use App\User;
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
     * creer un Agent
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required',
            'phone' => 'required|numeric|unique:users,phone',
            'adresse' => 'nullable',
            'description' => 'nullable',
            'email' => 'nullable|email',
        ]);
        if ($validator->fails()) {
            return response()->json([
                'message' => "Le formulaire contient des champs mal renseignés",
                'status' => false,
                'data' => null
            ]);
        }

        $role = Role::where('name', Roles::RESSOURCE)->first();

        $input = $request->all();

        $input['password'] = bcrypt("000000");
        $input['add_by'] = Auth::user()->id;
        $input['statut'] = Statut::APPROUVE;
        $input['avatar'] = null;

        $user = User::create($input);
        //

        if (isset($user)) {
            //On crée la caisse de l'utilisateur
            $caisse = new Caisse([
                'nom' => 'Caisse ' . $request->name,
                'description' => Null,
                'id_user' => $user->id,
                'reference' => Null,
                'solde' => 0
            ]);
            $caisse->save();

            //on lui donne un role
            $user->assignRole($role);

            //On lui crée un token
            $success['token'] =  $user->createToken('MyApp')->accessToken;
            $success['user'] =  $user;

            $user->setting()->create([
                'bars' => '[0,1,2,3,4,5,6,7,8,9]',
                'charts' => '[0,1,2,3,4,5,6,7,8,9]',
                'cards' => '[0,1,2,3,4,5,6,7,8,9]',
            ]);

            return response()->json([
                'message' => 'Ressource crée avec succès',
                'status' => true,
                'data' => [
                    'resource' => $user,
                    'createur' => $user->creator
                ]
            ]);
        }

        return response()->json([
            'message' => "Erreur lors de création de la ressource",
            'status' => false,
            'data' => null
        ]);
    }

    /**
     * liste des Agents
     *
     * @return JsonResponse
     */
    public function list()
    {
        $resources = User::orderBy('created_at', 'desc')->get()->filter(function(User $user) {
            return ($user->roles->first()->name === Roles::RESSOURCE);
        });

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'resources' => $this->resourcesResponse($resources),
                'hasMoreData' => false,
            ]
        ]);
    }

    /**
     * Liste des Agents
     */
    // SUPERVISOR
    public function list_all()
    {
        $resources = User::orderBy('created_at', 'desc')->get()->filter(function(User $user) {
            return ($user->roles->first()->name === Roles::RESSOURCE);
        });

        return response()->json([
            'message' => '',
            'status' => true,
            'data' => [
                'resources' => $this->resourcesResponse($resources)
            ]
        ]);
    }

    // Build resources return data
    private function resourcesResponse($resources)
    {
        $returenedResources = [];

        foreach($resources as $resource) {

            $returenedResources[] = [
                'resource' => $resource,
                'caisse' => Caisse::where('id_user', $resource->id)->first(),
                'createur' => $resource->creator
            ];
        }

        return $returenedResources;
    }
}

<?php

namespace App\Http\Controllers\API;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; 
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;
use App\Enums\Roles;

class RoleController extends Controller
{


    public function __construct(){

        $superviseur = Roles::SUPERVISEUR;
        $this->middleware("permission:$superviseur");
        
    }


    

/**

     * Liste des permissions

     */

    public function permisions_list()
    {
        //recuperation des roles
        $roles = Role::all();

        if ($roles != null) {
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['roles' => $roles]
                ]
            );
         }else{
            return response()->json(
                [
                    'message' => 'Aucun role trouve',
                    'status' => false,
                    'data' => null
                ]
            ); 
         }
         

    }



    /**

     * Creer un Role

     *
     */

    public function store(Request $request)

    {

        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'name' => 'required|unique:roles,name',
            'permission' => 'required',
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

        //si le role est créé
        if($role = Role::create(['name' => $request->input('name')])){

            //Attribuer une permission au role
            $role->givePermissionTo($request->input('permission'));

            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['role' => $role]
                ]
            );
        }else {
                return response()->json(
                [
                    'message' => 'Erreur de creation du role',
                    'status' => false,
                    'data' => null
                ]
            );
        }     

        

    }




       /**

     * details d'un role

     */

    public function show($id)

    {

        //On cherche le role
        $role = Role::find($id);

        if($role != null){
            //On recupère les permeiisions
            $permissions = Permission::get();

            //On recupère les permeiisions du role
            $rolePermissions = Permission::join("role_has_permissions","role_has_permissions.permission_id","=","permissions.id")

            ->where("role_has_permissions.role_id",$id)

            ->get();

            
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['role' => $role, 'rolePermissions' => $rolePermissions, 'permissions' => $permissions]
                ]
            );
        }

        //On retourne une erreur
        return response()->json(
            [
                'message' => 'Aucun role trouvé',
                'status' => false,
                'data' => null
            ]
        );

    }



        /**

     * Modifier un role.

     */

    public function update(Request $request, $id)

    {

        // Valider données envoyées
        $validator = Validator::make($request->all(), [ 
            'name' => 'required',
            'permission' => 'required',
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

        // si le role est trouvé
        if($role = Role::find($id)){
            $role->name = $request->input('name');
            $role->save();

            // On attribut la permission
            $role->givePermissionTo($request->input('permission'));
            return response()->json(
                [
                    'message' => '',
                    'status' => true,
                    'data' => ['role' => $role]
                ]
            ); 
        }

        //On retourne une erreur
        return response()->json(
            [
                'message' => 'echec de la modification',
                'status' => false,
                'data' => null
            ]
        ); 

    }



    /**

     * supprimer un role

     */

    public function destroy($id)

    {

        // si le role est supprimé
        if(DB::table("roles")->where('id',$id)->delete()){            
            return response()->json(
                [
                    'message' => 'Role supprimé',
                    'status' => true,
                    'data' => null
                ]
            ); 
        }

        //On retourne une erreur
        return response()->json(
            [
                'message' => 'echec de la suppression',
                'status' => false,
                'data' => null
            ]
        ); 

    }
}

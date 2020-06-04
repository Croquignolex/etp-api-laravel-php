<?php

namespace App\Http\Controllers\API;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator; 
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\Controller;


class RoleController extends Controller
{


    public function __construct(){

        $this->middleware('permission:superviseur');
        
    }


    

/**

     * Liste des permissions

     */

    public function permisions_list()

    {

        //recuperation des permissions
        $permissions = Permission::get('name');

        //recuperation des roles
        $Roles = Role::get('name');

        if ($permissions != Null) {
            return response()->json(['permissions' => $permissions, 'Roles' => $Roles, 'status'=>200], 200);
         }else{
            return response()->json(['error' => 'Aucune permission trouvee', 'status'=>500], 500); 
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
                    return response()->json(['error'=>$validator->errors(), 'status'=>401], 401);            
                }

        //si le role est créé
        if($role = Role::create(['name' => $request->input('name')])){

            //Attribuer une permission au role
            $role->givePermissionTo($request->input('permission'));

            return response()->json(['success' => $role, 'status'=>200], 200);
        }      

        return response()->json(['error' => 'Erreur de creation du role', 'status'=>500], 500);

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

            return response()->json(['Success' => $role, 'rolePermissions' => $rolePermissions, 'permissions' => $permissions, 'status'=>200], 200);
        }

        //On retourne une erreur
        return response()->json(['error' => 'Aucun role trouvé', 'status'=>204], 204);

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
                    return response()->json(['error'=>$validator->errors(), 'status'=>401], 401);            
                }

        // si le role est trouvé
        if($role = Role::find($id)){
            $role->name = $request->input('name');
            $role->save();

            // On attribut la permission
            $role->givePermissionTo($request->input('permission'));

            return response()->json(['Success' => $role, 'status'=>200], 200);
        }

        //On retourne une erreur
        return response()->json(['error' => 'echec de la modification', 'status'=>500], 500);

    }



    /**

     * supprimer un role

     */

    public function destroy($id)

    {

        // si le role est supprimé
        if(DB::table("roles")->where('id',$id)->delete()){            
            return response()->json(['Success' => "role supprimé", 'status'=>200], 200);
        }

        //On retourne une erreur
        return response()->json(['error' => 'echec de la suppression', 'status'=>500], 500);

    }
}

<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use App\User;
use Spatie\Permission\Models\Permission;

class CreateAdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()

    {

        //creer l'admin

        $user = User::create([

        	'name' => 'Admin', 

            'email' => 'admin@gmail.com',
            
            'phone' => '690444983', 

            'adresse' => 'Douala, Ndokotti',

        	'password' => bcrypt('123456')

        ]);

  
            //creer les roles
        $role = Role::create(['name' => 'Admin']);
        $role2 = Role::create(['name' => 'superviseur']);
        $role3 = Role::create(['name' => 'gerant']);
        $role4 = Role::create(['name' => 'recouvreur']);
        $role5 = Role::create(['name' => 'Agent']);
      

        //recuperer toutes les premissions
        $permissions = Permission::pluck('id','id')->all();  

        //donner toutes ces permissions au role Admin 
        $role->syncPermissions($permissions); 
        
        //donner des permissions au autres roles
        $role2->givePermissionTo('superviseur');
        $role3->givePermissionTo('gerant');
        $role4->givePermissionTo('recouvreur');
        $role5->givePermissionTo('Agent');

        //Attribuer le role à l'utilisateur
        $user->assignRole([$role->id]);

    }
}

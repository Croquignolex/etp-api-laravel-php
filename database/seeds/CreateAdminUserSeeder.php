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

        $user2 = User::create([

        	'name' => 'AGENT', 

            'email' => 'agent@gmail.com',
            
            'phone' => '222222222', 

            'adresse' => 'Douala, Ndokotti',

        	'password' => bcrypt('123456')

        ]);

        $user3 = User::create([

        	'name' => 'GESTION_FLOTTE', 

            'email' => 'flotte@gmail.com',
            
            'phone' => '333333333', 

            'adresse' => 'Douala, Ndokotti',

        	'password' => bcrypt('123456')

        ]);

        $user4 = User::create([

        	'name' => 'RECOUVREUR', 

            'email' => 'recouvreur@gmail.com',
            
            'phone' => '444444444', 

            'adresse' => 'Douala, Ndokotti',

        	'password' => bcrypt('123456')

        ]);

        $user5 = User::create([

        	'name' => 'SUPERVISEUR', 

            'email' => 'supperviseur@gmail.com',
            
            'phone' => '555555555', 

            'adresse' => 'Douala, Ndokotti',

        	'password' => bcrypt('123456')

        ]);

  
            //creer les roles
        $role = Role::create(['name' => App\Enums\Roles::ADMIN]);
        $role2 = Role::create(['name' => App\Enums\Roles::AGENT]);
        $role3 = Role::create(['name' => App\Enums\Roles::GESTION_FLOTTE]);
        $role4 = Role::create(['name' => App\Enums\Roles::RECOUVREUR]);
        $role5 = Role::create(['name' => App\Enums\Roles::SUPERVISEUR]); 
      

        //recuperer toutes les premissions
        $permissions = Permission::pluck('id','id')->all();  

        //donner toutes ces permissions au role Admin 
        $role->syncPermissions($permissions); 
        
        //donner des permissions au autres roles
        $role2->givePermissionTo(App\Enums\Roles::AGENT);
        $role3->givePermissionTo(App\Enums\Roles::GESTION_FLOTTE);
        $role4->givePermissionTo(App\Enums\Roles::RECOUVREUR);
        $role5->givePermissionTo(App\Enums\Roles::SUPERVISEUR);

        //Attribuer le role à l'utilisateur
        $user->assignRole([$role->id]);

        //Attribuer le role à l'agent1
        $user2->assignRole([$role2->id]);

        //Attribuer le role à l'agent2
        $user3->assignRole([$role3->id]);

        //Attribuer le role à l'agent1
        $user4->assignRole([$role4->id]);

        //Attribuer le role à l'agent2
        $user5->assignRole([$role5->id]);

    }
}

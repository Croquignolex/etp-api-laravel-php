<?php

use App\Puce;
use App\Zone;
use App\User;
use App\Setting;
use App\Type_puce;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
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
        //creer l'admin et les autre utilisateurs par defaut

        $user = User::create([

        	'name' => 'Admin',

            'email' => 'admin@etp.com',

            'phone' => '600000000',

            'adresse' => 'Douala, Ndokotti',

        	'password' => bcrypt('123456')

        ]);

        $user2 = User::create([

        	'name' => 'AGENT JOE MANI',

            'email' => 'agent1@etp.com',

            'phone' => '622222222',

            'adresse' => 'Douala, Ndokotti',

        	'password' => bcrypt('123456'),

			'id_zone' => 1,

            'add_by' => 1
        ]);

		$user22 = User::create([

        	'name' => 'RESSOURCE EMI WHITE',

            'email' => 'agent2@etp.com',

            'phone' => '611111111',

            'adresse' => 'Douala, Ndokotti',

        	'password' => bcrypt('123456'),

			'id_zone' => 1,

            'add_by' => 1
        ]);

        $user3 = User::create([

        	'name' => 'GESTIONNAIRE CLARISSE JOKO',

            'email' => 'gestionnaire_flote@etp.com',

            'phone' => '633333333',

            'adresse' => 'Douala, Ndokotti',

        	'password' => bcrypt('123456'),

            'add_by' => 1
        ]);

        $user4 = User::create([

        	'name' => 'RESPONSABLE EMMA NUIP',

            'email' => 'agent_recouvrement@etp.com',

            'phone' => '644444444',

            'adresse' => 'Douala, Ndokotti',

        	'password' => bcrypt('123456'),

			'id_zone' => 1,

            'add_by' => 1
        ]);

        $user5 = User::create([

        	'name' => 'SUPPERVISEUR MIREILLE KIKI',

            'email' => 'supperviseur@etp.com',

            'phone' => '655555555',

            'adresse' => 'Douala, Ndokotti',

        	'password' => bcrypt('123456'),

            'add_by' => 1
        ]);


        ///creation des types de puce par defaut

        Type_puce::create([

        	'name' => \App\Enums\Statut::AGENT

        ]);

        Type_puce::create([

        	'name' => \App\Enums\Statut::RESOURCE

        ]);

        Type_puce::create([

        	'name' => \App\Enums\Statut::FLOTTAGE

        ]);

        Type_puce::create([

        	'name' => \App\Enums\Statut::FLOTTAGE_SECONDAIRE

        ]);

        Type_puce::create([

            'name' => \App\Enums\Statut::CORPORATE

        ]);

        Type_puce::create([

            'name' => \App\Enums\Statut::PUCE_RZ

        ]);



        ///creation de la puce de distribution de flotte par defaut

        Puce::create([

        	'id_flotte' => 1,

			'numero' => '676000000',

            'id_agent' => Null,

            'type' => 3,

            'nom' => "Puce de flottage MTN"

        ]);

        Puce::create([

            'id_flotte' => 2,

			'numero' => '695000000',

            'type' => 3,

            'id_agent' => Null,

            'nom' => "Puce de flottage ORANGE"

        ]);

         Puce::create([

            'id_flotte' => 1,

			'numero' => '674000000',

            'type' => 4,

            'id_agent' => Null,

            'nom' => "MASTER SIM MTN"
        ]);

         Puce::create([

            'id_flotte' => 2,

            'numero' => '694000000',

            'type' => 4,

            'id_agent' => Null,

            'nom' => "MASTER SIM ORANGE"
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
            //à l'agent
                $role2->givePermissionTo(App\Enums\Roles::AGENT);
            //au gestionnaire de flotte
                $role3->givePermissionTo(App\Enums\Roles::GESTION_FLOTTE);
//                $role3->givePermissionTo(App\Enums\Roles::AGENT);
            //au supperviseur
                $role5->givePermissionTo(App\Enums\Roles::SUPERVISEUR);
//                $role5->givePermissionTo(App\Enums\Roles::GESTION_FLOTTE);
//                $role5->givePermissionTo(App\Enums\Roles::AGENT);



        $role4->givePermissionTo(App\Enums\Roles::RECOUVREUR);


        //Attribuer le role à l'utilisateur
        $user->assignRole([$role->id]);

        //Attribuer le role à l'agent1
        $user2->assignRole([$role2->id]);
		$user22->assignRole([$role2->id]);

        //Attribuer le role à l'agent2
        $user3->assignRole([$role3->id]);

        //Attribuer le role à l'agent1
        $user4->assignRole([$role4->id]);

        //Attribuer le role à l'agent2
        $user5->assignRole([$role5->id]);

		Setting::create([
			'cards' => '[0,1,2,3,4,5,6,7,8,9]',
			'charts' => '[0,1,2,3,4,5,6,7,8,9]',
			'bars' => '[0,1,2,3,4,5,6,7,8,9]',
			'id_user' => $user->id
		]);

		Setting::create([
			'cards' => '[0,1,2,3,4,5,6,7,8,9]',
			'charts' => '[0,1,2,3,4,5,6,7,8,9]',
			'bars' => '[0,1,2,3,4,5,6,7,8,9]',
			'id_user' => $user5->id
		]);

		Setting::create([
			'cards' => '[0,1,2,3,4,5,6,7,8,9]',
			'charts' => '[0,1,2,3,4,5,6,7,8,9]',
			'bars' => '[0,1,2,3,4,5,6,7,8,9]',
			'id_user' => $user2->id
		]);

		Setting::create([
			'cards' => '[0,1,2,3,4,5,6,7,8,9]',
			'charts' => '[0,1,2,3,4,5,6,7,8,9]',
			'bars' => '[0,1,2,3,4,5,6,7,8,9]',
			'id_user' => $user22->id
		]);

		Setting::create([
			'cards' => '[0,1,2,3,4,5,6,7,8,9]',
			'charts' => '[0,1,2,3,4,5,6,7,8,9]',
			'bars' => '[0,1,2,3,4,5,6,7,8,9]',
			'id_user' => $user3->id
		]);

		Setting::create([
			'cards' => '[0,1,2,3,4,5,6,7,8,9]',
			'charts' => '[0,1,2,3,4,5,6,7,8,9]',
			'bars' => '[0,1,2,3,4,5,6,7,8,9]',
			'id_user' => $user4->id
		]);

        Zone::create([
            'nom' => 'Douala',
            'id_responsable' => 5,
            'map' => '<iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d127357.54605115415!2d9.671763356449453!3d4.036071988407635!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x1061128be2e1fe6d%3A0x92daa1444781c48b!2sDouala!5e0!3m2!1sfr!2scm!4v1594723557579!5m2!1sfr!2scm" width="600" height="450" frameborder="0" style="border:0;" allowfullscreen="" aria-hidden="false" tabindex="0"></iframe>'
        ]);
    }
}

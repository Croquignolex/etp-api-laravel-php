<?php

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;

class PermissionTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()

    {

       $permissions = [

           'Superviseur',

           'Gerant',

           'Recouvreur',

           'Agent'

        ];

        //creer les differentes permissions
        foreach ($permissions as $permission) {

             Permission::create(['name' => $permission]);

        }

    }
}

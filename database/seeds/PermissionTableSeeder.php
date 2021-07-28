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

        App\Enums\Roles::AGENT,

        App\Enums\Roles::GESTION_FLOTTE,

        App\Enums\Roles::RECOUVREUR,

        App\Enums\Roles::SUPERVISEUR,
        App\Enums\Roles::CONTROLLEUR,
        App\Enums\Roles::COMPATBLE

        ];

        //creer les differentes permissions
        foreach ($permissions as $permission) {

             Permission::create(['name' => $permission]);

        }

    }
}

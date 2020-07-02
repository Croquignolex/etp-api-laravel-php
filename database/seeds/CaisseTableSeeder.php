<?php

use Illuminate\Database\Seeder;
use App\Caisse;

class CaisseTableSeeder extends Seeder {

	public function run()
	{
		//DB::table('caisses')->delete();

		// 'caisse1'
		Caisse::create(array(
			'nom' => 'Caisse Admin',
			'description' => Null,
			'id_user' => 1,
			'reference' => Null,
			'solde' => 0
			));

		// 'caisse2'
		Caisse::create(array(
			'nom' => 'Caisse AGENT',
			'description' => Null,
			'id_user' => 2,
			'reference' => Null,
			'solde' => 0
			));

		// 'caisse1'
		Caisse::create(array(
			'nom' => 'Caisse GESTION_FLOTTE',
			'description' => Null,
			'id_user' => 3,
			'reference' => Null,
			'solde' => 0
		));

		// 'caisse2'
		Caisse::create(array(
			'nom' => 'Caisse RECOUVREUR',
			'description' => Null,
			'id_user' => 4,
			'reference' => Null,
			'solde' => 0
		));

		// 'caisse5'
		Caisse::create(array(
			'nom' => 'Caisse SUPERVISEUR',
			'description' => Null,
			'id_user' => 5,
			'reference' => Null,
			'solde' => 0
		));


	}
}
<?php

use Illuminate\Database\Seeder;
use App\Caisse;

class CaisseTableSeeder extends Seeder {

	public function run()
	{
		//DB::table('caisses')->delete();

		// 'caisse1'
		Caisse::create(array(
				'nom' => 'Caisse 1',
				'description' => 'la caisse de recetion pour MoMo',
				'reference' => 'momo'
			));

		// 'caisse2'
		Caisse::create(array(
				'nom' => 'Caisse 2',
				'description' => 'la caisse de recetion pour Canal sat',
				'reference' => 'canal'
			));
	}
}
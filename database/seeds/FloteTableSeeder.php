<?php

use Illuminate\Database\Seeder;
use App\Flote;

class FloteTableSeeder extends Seeder {

	public function run()
	{
		//DB::table('flotes')->delete();

		// 'flote1'
		Flote::create(array(
				'nom' => \App\Enums\Statut::MTN,
				'description' => 'opérateur MTN'
			));

		// 'flote2'
		Flote::create(array(
				'nom' => \App\Enums\Statut::ORANGE,
				'description' => 'opérateur Orange'
			));
	}
}
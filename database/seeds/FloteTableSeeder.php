<?php

use App\Flote;
use Illuminate\Database\Seeder;

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

		// vendors
        \App\Vendor::create(array(
            'name' => \App\Enums\Statut::BY_DIGIT_PARTNER
        ));

        \App\Vendor::create(array(
            'name' => \App\Enums\Statut::BY_BANK
        ));
	}
}

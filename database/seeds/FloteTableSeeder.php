<?php

use App\Enums\Statut;
use App\Flote;
use App\Vendor;
use Illuminate\Database\Seeder;

class FloteTableSeeder extends Seeder {

	public function run()
	{
		// 'flote1'
		Flote::create([
            'nom' => Statut::MTN,
            'description' => 'Opérateur MTN'
        ]);

		// 'flote2'
		Flote::create([
            'nom' => Statut::ORANGE,
            'description' => 'Opérateur Orange'
        ]);

		// vendors1
        Vendor::create([
            'name' => Statut::BY_DIGIT_PARTNER,
            'description' => Statut::BY_DIGIT_PARTNER
        ]);

        // vendors2
        Vendor::create([
            'name' => Statut::BY_BANK,
            'description' => Statut::BY_BANK
        ]);

        // vendors3
        Vendor::create([
            'name' => "BGFT",
            'description' => "BGFT"
        ]);
	}
}

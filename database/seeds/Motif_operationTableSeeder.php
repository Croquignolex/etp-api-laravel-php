<?php

use Illuminate\Database\Seeder;
use App\Motif_operation;

class Motif_operationTableSeeder extends Seeder {

	public function run()
	{
		//DB::table('motif_operations')->delete();

		// 'motif1'
		Motif_operation::create(array(
				'nom' => 'paiement du loyer',
				'description' => 'paiement du loyer'
			));

		// 'motif2'
		Motif_operation::create(array(
				'nom' => 'payement internet',
				'description' => 'payement internet'
			));
	}
}
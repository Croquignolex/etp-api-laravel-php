<?php

use Illuminate\Database\Seeder;
use App\Type_transaction;

class Type_transactionTableSeeder extends Seeder {

	public function run()
	{
		//DB::table('type_transactions')->delete();

		// 'depot'
		Type_transaction::create(array( 
				'nom' => App\Enums\Transations::DEMANDE_FLOTTE
			));

		// 'retrait'
		Type_transaction::create(array(
			'nom' => App\Enums\Transations::DEMANDE_DESTOCK
			));

		// 'approvi'
		Type_transaction::create(array(
			'nom' => App\Enums\Transations::APPROVISIONNEMENT
			));

		// 'deappro'
		Type_transaction::create(array(
			'nom' => App\Enums\Transations::DESTOCKAGE
			));

		// 'destock'
		Type_transaction::create(array(
			'nom' => App\Enums\Transations::RECOUVREMENT
			));

		// 'DEMANDE_FLOTTE'
		Type_transaction::create(array(
			'nom' => App\Enums\Transations::RETOUR_FLOTTE
			));

	}
}
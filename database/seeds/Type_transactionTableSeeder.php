<?php

use Illuminate\Database\Seeder;
use App\Type_transaction;

class Type_transactionTableSeeder extends Seeder {

	public function run()
	{
		//DB::table('type_transactions')->delete();

		// 'depot'
		Type_transaction::create(array(
				'nom' => 'Depot'
			));

		// 'retrait'
		Type_transaction::create(array(
				'nom' => 'Retrait'
			));

		// 'approvi'
		Type_transaction::create(array(
				'nom' => 'Approvisionnement'
			));

		// 'deappro'
		Type_transaction::create(array(
				'nom' => 'reglement'
			));

		// 'destock'
		Type_transaction::create(array(
				'nom' => 'Destockage'
			));

		// 'retour_flote'
		Type_transaction::create(array(
				'nom' => 'Retour flote'
			));

			// 'recouvrement'
		Type_transaction::create(array(
			'nom' => 'Retour flote'
		));

		// 'abonn'
		Type_transaction::create(array(
				'nom' => 'Abonnement'
			));

		// 'reabonn'
		Type_transaction::create(array(
				'nom' => 'Reabonnement'
			));
	}
}
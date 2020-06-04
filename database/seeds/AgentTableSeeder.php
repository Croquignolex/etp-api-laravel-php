<?php

use Illuminate\Database\Seeder;
use App\Agent;

class AgentTableSeeder extends Seeder {

	public function run()
	{
		//DB::table('agents')->delete();

		// 'agent'
		Agent::create(array(
				'nom' => 'stivo',
				'phone' => '690444983',
				'adresse' => 'Ndogbong',
				'taux_commission' => 20,
				'pays' => 'Camer'
			));

		// 'agent_2'
		Agent::create(array(
				'nom' => 'jyress',
				'phone' => '695365567',
				'adresse' => 'Ndokotti',
				'taux_commission' => 30,
				'pays' => 'Benin'
			));
	}
}
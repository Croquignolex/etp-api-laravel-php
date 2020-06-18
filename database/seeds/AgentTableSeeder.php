<?php

use Illuminate\Database\Seeder;
use App\Agent;

class AgentTableSeeder extends Seeder {

	public function run()
	{
		//DB::table('agents')->delete();

		// 'agent'
		Agent::create(array(
			'id_creator' => '1',
			'id_user' => '2',
			'img_cni' => null,
			'img_cni_back' => null,
			'reference' => 'd6070',
			'taux_commission' => 50,
			'ville' => 'Douala',
			'point_de_vente' => 'Bepanda',
			'puce_name' => 'default puce name',
			'puce_number' => 699999999,
			'pays' => 'Cameroun'
			));

		// 'agent_2'
		Agent::create(array(
			'id_creator' => '1',
			'id_user' => '2',
			'img_cni' => null,
			'img_cni_back' => null,
			'reference' => 'd6020',
			'taux_commission' => 30,
			'ville' => 'Douala',
			'point_de_vente' => 'Bonabery',
			'puce_name' => 'default puce name 2',
			'puce_number' => 677777777,
			'pays' => 'Cameroun'
			));
	}
}

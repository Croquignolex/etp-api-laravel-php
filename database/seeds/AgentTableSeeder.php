<?php

use App\Puce;
use App\Agent;
use App\Enums\Roles;
use Illuminate\Database\Seeder;

class AgentTableSeeder extends Seeder {

	public function run()
	{
		// 'agent_2'
		$agent_par_defaut = Agent::create(array(
			'id_creator' => '1',
			'id_user' => '3',
			'img_cni' => null,
			'img_cni_back' => null,
			'reference' => Roles::RESSOURCE,
			'taux_commission' => 30,
			'ville' => 'Douala',
			'point_de_vente' => 'Bonabery',
			'pays' => 'Cameroun'
			));

		$agent_par_defaut2 = Agent::create(array(
			'id_creator' => '1',
			'id_user' => '2',
			'img_cni' => null,
			'img_cni_back' => null,
			'reference' => Roles::AGENT,
			'taux_commission' => 20,
			'ville' => 'Douala',
			'point_de_vente' => 'Round point',
			'pays' => 'Cameroun'
			));

			// 'Puce de l'agent par defaut mtn'
			Puce::create([

				'id_flotte' => 1,

				'numero' => '671000000',

				'type' => 1,

				'id_agent' => $agent_par_defaut2->id,

				'nom' => 'JOE MANI'

			]);

			// 'Puce de l'agent par defaut orange'
			Puce::create([

				'id_flotte' => 2,

				'numero' => '692000000',

				'type' => 1,

				'id_agent' => $agent_par_defaut2->id,

				'nom' => 'JOE MANI'

			]);

			Puce::create([

				'id_flotte' => 1,

				'numero' => '673000000',

				'type' => 2,

				'id_agent' => $agent_par_defaut->id,

				'nom' => 'EMI WHITE'

			]);
	}
}

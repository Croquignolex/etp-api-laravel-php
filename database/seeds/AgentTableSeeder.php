<?php

use Illuminate\Database\Seeder;
use App\Agent;
use App\Puce;

class AgentTableSeeder extends Seeder {

	public function run()
	{


		// 'agent_2'
		$agent_par_defaut = Agent::create(array(
			'id_creator' => '1',
			'id_user' => '2',
			'img_cni' => null,
			'img_cni_back' => null,
			'reference' => 'd6020',
			'taux_commission' => 30,
			'ville' => 'Douala',
			'point_de_vente' => 'Bonabery',
			'pays' => 'Cameroun'
			));

			// 'Puce de l'agent par defaut mtn'
			$puce_agent_par_defaut = Puce::create([

				'id_flotte' => 1, 
				
				'type' => 1,
	
				'id_agent' => $agent_par_defaut->id,

				'nom' => \App\Enums\Statut::MTN
	
			]);
			
			// 'Puce de l'agent par defaut orange'
			$puce_agent_par_defaut = Puce::create([

				'id_flotte' => 2, 
				
				'type' => 1,
	
				'id_agent' => $agent_par_defaut->id,

				'nom' => \App\Enums\Statut::ORANGE
	
			]);
	}
}

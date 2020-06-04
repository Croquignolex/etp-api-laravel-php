<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
        // $this->call(UsersTableSeeder::class);
		Model::unguard();

		$this->call('Type_transactionTableSeeder');
		$this->command->info('Type_transaction table seeded!');

		$this->call('FloteTableSeeder');
		$this->command->info('Flote table seeded!');

		$this->call('CaisseTableSeeder');
		$this->command->info('Caisse table seeded!');

		$this->call('AgentTableSeeder');
		$this->command->info('Agent table seeded!');

		$this->call('Motif_operationTableSeeder');
		$this->command->info('Motif_operation table seeded!');
    }
}

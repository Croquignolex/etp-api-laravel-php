<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTransactionsTable extends Migration {

	public function up()
	{
		Schema::create('transactions', function(Blueprint $table) {
			$table->increments('id');
			$table->integer('id_transaction')->unsigned()->nullable()->index();
			$table->integer('id_user')->unsigned()->nullable()->index();
			$table->integer('id_versement')->unsigned()->nullable()->index();
			$table->integer('id_type_transaction')->unsigned()->nullable()->index();
			$table->integer('id_puce')->unsigned()->nullable()->index();
			$table->integer('montant')->unsigned()->default('0');
			$table->integer('reste')->unsigned()->nullable()->default('0');
			$table->string('statut')->nullable();
			$table->integer('user_destination')->unsigned()->nullable()->index();
			$table->integer('user_source')->unsigned()->nullable()->index();
			$table->timestamps();
			$table->softDeletes();
			
		});
	}

	public function down()
	{
		Schema::drop('transactions');
	}
}
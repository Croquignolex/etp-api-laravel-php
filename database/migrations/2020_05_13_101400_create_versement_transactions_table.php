<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVersementTransactionsTable extends Migration {

	public function up()
	{
		Schema::create('versement_transactions', function(Blueprint $table) {
			$table->increments('id');
			$table->integer('id_transaction')->unsigned()->nullable()->index();
			$table->integer('id_versement')->unsigned()->nullable()->index();
			$table->timestamps();
			$table->softDeletes();
			
		});
	}

	public function down()
	{
		Schema::drop('versement_transactions');
	}
}
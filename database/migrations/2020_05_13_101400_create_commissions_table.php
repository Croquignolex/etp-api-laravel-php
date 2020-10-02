<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCommissionsTable extends Migration {

	public function up()
	{
		Schema::create('commissions', function(Blueprint $table) {
			$table->increments('id');
			$table->integer('id_transaction')->unsigned()->nullable();
			$table->double('montant')->nullable();
			$table->string('statut')->nullable();
			$table->timestamps();
			$table->softDeletes();
			
		});
	}

	public function down()
	{
		Schema::drop('commissions');
	}
}
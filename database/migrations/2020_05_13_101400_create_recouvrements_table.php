<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRecouvrementsTable extends Migration {

	public function up()
	{
		Schema::create('recouvrements', function(Blueprint $table) {
			$table->increments('id');
			$table->integer('id_user')->unsigned()->nullable()->index();
			$table->integer('id_versement')->unsigned()->nullable()->index();
			$table->integer('id_transaction')->unsigned()->nullable()->index();
			$table->integer('id_flottage')->unsigned()->nullable()->index();
			$table->double('montant')->unsigned()->default('0');
			$table->integer('reste')->unsigned()->nullable()->default('0');
			$table->string('type_transaction')->nullable();
			$table->string('reference')->nullable();
			$table->string('statut')->nullable();
			$table->integer('user_destination')->unsigned()->nullable()->index();
			$table->integer('user_source')->unsigned()->nullable()->index();
			$table->timestamps();
			$table->softDeletes();
			
			
		});
	}

	public function down()
	{
		Schema::drop('recouvrements');
	}
}
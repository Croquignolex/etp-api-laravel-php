<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApprovisionnementsTable extends Migration {

	public function up()
	{
		Schema::create('approvisionnements', function(Blueprint $table) {
			$table->increments('id');
			$table->integer('id_demande_flote')->unsigned()->nullable()->index();
			$table->integer('id_user')->unsigned()->nullable()->index();
			$table->string('reference')->nullable();
			$table->string('statut')->nullable();
			$table->string('note')->nullable();
			$table->integer('montant')->nullable();
			$table->integer('reste')->nullable();
			$table->timestamps();
			$table->softDeletes();
			
		});
	}

	public function down()
	{
		Schema::drop('approvisionnements');
	}
}
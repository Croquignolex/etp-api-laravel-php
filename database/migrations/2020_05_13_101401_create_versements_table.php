<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateVersementsTable extends Migration {

	public function up()
	{
		Schema::create('versements', function(Blueprint $table) {
			$table->increments('id');
			$table->integer('id_caisse')->unsigned()->nullable()->index();
			$table->integer('correspondant')->unsigned()->nullable()->index();
			$table->integer('add_by')->unsigned()->nullable()->index();
			$table->double('montant')->nullable();
			$table->text('note')->nullable();
			$table->string('recu')->nullable();
			$table->timestamps();
			$table->softDeletes();

		});
	}

	public function down()
	{
		Schema::drop('versements');
	}
}
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateOperationsTable extends Migration {

	public function up()
	{
		Schema::create('operations', function(Blueprint $table) {
			$table->increments('id');
			$table->integer('id_versement')->unsigned()->nullable()->index();
			$table->integer('id_motif')->unsigned()->nullable()->index();
			$table->integer('id_user')->unsigned()->nullable()->index();
			$table->text('description')->nullable();
			$table->boolean('flux')->nullable();
			$table->softDeletes();
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::drop('operations');
	}
}
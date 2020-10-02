<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCaissesTable extends Migration {

	public function up()
	{
		Schema::create('caisses', function(Blueprint $table) {
			$table->increments('id');
			$table->integer('id_user')->unsigned()->nullable()->index();
			$table->string('nom')->nullable();
			$table->string('description')->nullable();
			$table->double('solde')->nullable();
			$table->string('reference')->nullable();
			$table->softDeletes();
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::drop('caisses');
	}
}
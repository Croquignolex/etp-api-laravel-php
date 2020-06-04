<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateAgentsTable extends Migration {

	public function up()
	{
		Schema::create('agents', function(Blueprint $table) {
			$table->increments('id');
			$table->string('nom')->nullable();
			$table->string('img_cni')->nullable();
			$table->string('phone')->nullable();
			$table->string('reference')->nullable();
			$table->string('adresse')->nullable();
			$table->decimal('taux_commission')->nullable();
			$table->string('email')->nullable();
			$table->string('pays')->nullable();
			$table->softDeletes();
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::drop('agents');
	}
}
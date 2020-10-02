<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateAgentsTable extends Migration {

	public function up()
	{
		Schema::create('agents', function(Blueprint $table) {
			$table->increments('id');
			$table->integer('id_creator')->unsigned()->nullable()->index();
			$table->integer('id_user')->unsigned()->nullable()->index();
			$table->string('img_cni')->nullable();
			$table->string('reference')->nullable();
			$table->decimal('taux_commission')->nullable();
			$table->string('ville')->nullable();
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
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateFlotesTable extends Migration {

	public function up()
	{
		Schema::create('flotes', function(Blueprint $table) {
			$table->increments('id');
			$table->string('nom')->nullable();
			$table->string('reference')->nullable();
			$table->string('description')->nullable();
			$table->decimal('solde')->nullable()->default(0);
			$table->softDeletes();
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::drop('flotes');
	}
}
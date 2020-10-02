<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFlotesTable extends Migration {

	public function up()
	{
		Schema::create('flotes', function(Blueprint $table) {
			$table->increments('id');
			$table->string('nom')->nullable();
			$table->string('description')->nullable();
			$table->softDeletes();
			$table->timestamps(); 
		});
	}

	public function down()
	{
		Schema::drop('flotes');
	}
}
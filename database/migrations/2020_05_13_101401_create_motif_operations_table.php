<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMotifOperationsTable extends Migration {

	public function up()
	{
		Schema::create('motif_operations', function(Blueprint $table) {
			$table->increments('id');
			$table->string('nom')->nullable();
			$table->text('description');
			$table->softDeletes();
			$table->timestamps();
		});
	}

	public function down()
	{
		Schema::drop('motif_operations');
	}
}
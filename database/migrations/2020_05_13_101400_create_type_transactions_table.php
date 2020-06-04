<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateTypeTransactionsTable extends Migration {

	public function up()
	{
		Schema::create('type_transactions', function(Blueprint $table) {
			$table->increments('id');
			$table->string('nom')->nullable();
			$table->text('description')->nullable();
			$table->timestamps();
			$table->softDeletes();
		});
	}

	public function down()
	{
		Schema::drop('type_transactions');
	}
}
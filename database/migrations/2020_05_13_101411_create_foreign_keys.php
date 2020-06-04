<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Eloquent\Model;

class CreateForeignKeys extends Migration {

	public function up()
	{
		Schema::table('commissions', function(Blueprint $table) {
			$table->foreign('id_transaction')->references('id')->on('transactions')
						->onDelete('set null')
						->onUpdate('no action');
		});
	}

	public function down()
	{
		Schema::table('commissions', function(Blueprint $table) {
			$table->dropForeign('commissions_id_transaction_foreign');
		});
	}
}
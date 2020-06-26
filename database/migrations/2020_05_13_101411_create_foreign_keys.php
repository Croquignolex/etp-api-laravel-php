<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateForeignKeys extends Migration {

	public function up()
	{
		Schema::table('commissions', function(Blueprint $table) {
			$table->foreign('id_transaction')->references('id')->on('approvisionnements')
						->onDelete('set null')
						->onUpdate('no action');
		});
	}

	public function down()
	{
		Schema::table('commissions', function(Blueprint $table) {
			$table->dropForeign('commissions_id_approvisionnement_foreign');
		});
	}
}
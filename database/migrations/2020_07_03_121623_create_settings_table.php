<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateSettingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->increments('id');
			$table->string('cards');
			$table->string('charts');
			$table->string('bars');
			$table->boolean('sound')->default(true);
			$table->integer('session')->default(15);
			$table->string('description')->nullable();
			$table->integer('id_user')->unsigned();
			$table->softDeletes();
			$table->timestamps();
			
			$table->foreign('id_user')
                ->references('id')
                ->on('users')
				->onDelete('cascade')
				->onUpdate('no action');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('settings');
    }
}

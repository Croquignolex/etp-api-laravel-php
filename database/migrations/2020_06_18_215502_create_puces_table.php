<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreatePucesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('puces', function (Blueprint $table) {

            $table->bigIncrements('id');
            $table->integer('id_flotte')->unsigned()->nullable()->index();
            $table->integer('id_agent')->unsigned()->nullable()->index();
			$table->string('nom')->nullable();
            $table->string('reference')->nullable();
            $table->string('numero')->nullable();
			$table->string('description')->nullable();
			$table->decimal('solde')->nullable()->default(0);
			$table->softDeletes();
			$table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('puces');
    }
}

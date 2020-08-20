<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCorporatesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('corporates', function (Blueprint $table) {
  
            $table->increments('id');
			$table->string('nom');
			$table->string('phone');
            $table->string('responsable');
            $table->string('dossier')->nullable();
			$table->string('adresse')->nullable()->default(null);
			$table->string('numeros_agents')->nullable()->default(null);
			$table->string('description')->nullable()->default(null);
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
        Schema::dropIfExists('corporates');
    }
}

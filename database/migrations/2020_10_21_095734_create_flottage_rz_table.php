<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFlottageRzTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('flottage_rz', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_responsable_zone')->unsigned()->nullable()->index();
            $table->integer('id_agent')->unsigned()->nullable()->index();
            $table->integer('id_sim_agent')->unsigned()->nullable()->index();
			$table->string('reference')->nullable();
			$table->string('statut')->nullable();
			$table->double('montant')->nullable();
			$table->integer('reste')->nullable();
			$table->timestamps();
			$table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('flottage__rz');
    }
}

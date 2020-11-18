<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFlotageAnonymesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('flotage_anonymes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_user')->unsigned()->nullable()->index();
            $table->integer('id_sim_from')->unsigned()->nullable()->index();
            $table->string('nro_sim_to')->nullable();
            $table->string('reference')->nullable();
            $table->string('statut')->nullable();
            $table->string('nom_agent')->nullable();
            $table->double('montant')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_user')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('no action');

            $table->foreign('id_sim_from')
                ->references('id')
                ->on('puces')
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
        Schema::dropIfExists('flotage_anonymes');
    }
}

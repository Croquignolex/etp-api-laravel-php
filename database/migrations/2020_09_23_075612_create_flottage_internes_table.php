<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFlottageInternesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('flottage_internes', function (Blueprint $table) {

            $table->increments('id');
            $table->integer('id_user')->unsigned()->nullable()->index();
            $table->integer('id_sim_from')->unsigned()->nullable()->index();
            $table->integer('id_sim_to')->unsigned()->nullable()->index();
			$table->string('reference')->nullable();
			$table->string('statut')->nullable();
			$table->string('note')->nullable();
			$table->double('montant')->nullable();
			$table->integer('reste')->nullable();
			$table->timestamps();
			$table->softDeletes();

        });

        // pour la table flottage interne

        Schema::table('flottage_internes', function(Blueprint $table) {

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
        Schema::dropIfExists('flottage_internes');
    }
}

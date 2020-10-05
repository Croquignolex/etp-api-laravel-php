<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateForeingnKeys extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        // pour la table approvisionnements

        Schema::table('approvisionnements', function(Blueprint $table) {
			//migrations des clés
            $table->foreign('id_demande_flote')
                ->references('id')
                ->on('demande_flotes')
				->onDelete('cascade')
				->onUpdate('no action');


			$table->foreign('id_user')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
				->onUpdate('no action');
        });

         // pour la table recouvrements

        Schema::table('recouvrements', function(Blueprint $table) {
			//migrations des clés
            $table->foreign('id_user')
                ->references('id')
                ->on('users')
				->onDelete('cascade')
				->onUpdate('no action');

			$table->foreign('user_destination')
                ->references('id')
                ->on('users')
				->onDelete('cascade')
				->onUpdate('no action');

			$table->foreign('user_source')
                ->references('id')
                ->on('users')
				->onDelete('cascade')
				->onUpdate('no action');

			$table->foreign('id_versement')
                ->references('id')
                ->on('versements')
				->onDelete('cascade')
				->onUpdate('no action');
        });



        // pour la table versement_transactions
		Schema::table('versement_transactions', function(Blueprint $table) {
			//migrations des clés
            $table->foreign('id_transaction')
                ->references('id')
                ->on('recouvrements')
				->onDelete('cascade')
				->onUpdate('no action');

			$table->foreign('id_versement')
                ->references('id')
                ->on('versements')
				->onDelete('cascade')
				->onUpdate('no action');
        });


        // pour la table agents
		Schema::table('agents', function(Blueprint $table) {
			//migrations des clés
            $table->foreign('id_creator')
                ->references('id')
                ->on('users')
				->onDelete('cascade')
				->onUpdate('no action');

			$table->foreign('id_user')
                ->references('id')
                ->on('users')
				->onDelete('cascade')
				->onUpdate('no action');
        });


        // pour la table operations
		Schema::table('operations', function(Blueprint $table) {
			//migrations des clés
            $table->foreign('id_versement')
                ->references('id')
                ->on('versements')
				->onDelete('cascade')
				->onUpdate('no action');


			$table->foreign('id_motif')
                ->references('id')
                ->on('motif_operations')
				->onDelete('cascade')
				->onUpdate('no action');


			$table->foreign('id_user')
                ->references('id')
                ->on('users')
				->onDelete('cascade')
				->onUpdate('no action');
        });


        // pour la table versements
        Schema::table('versements', function(Blueprint $table) {
			//migrations des clés
            $table->foreign('id_caisse')
                ->references('id')
                ->on('caisses')
				->onDelete('cascade')
				->onUpdate('no action');


			$table->foreign('correspondant')
                ->references('id')
                ->on('users')
				->onDelete('cascade')
				->onUpdate('no action');

        });



        // pour la table users
        Schema::table('users', function(Blueprint $table) {
			//migrations des clés
            $table->foreign('add_by')
                ->references('id')
                ->on('users')
				->onDelete('cascade')
				->onUpdate('no action');

			 $table->foreign('id_zone')
                ->references('id')
                ->on('zones')
				->onDelete('cascade')
				->onUpdate('no action');
        });

        // pour la table puces
        Schema::table('puces', function(Blueprint $table) {
			//migrations des clés
            $table->foreign('id_flotte')
                ->references('id')
                ->on('flotes')
                ->onDelete('cascade')
				->onUpdate('no action');

            $table->foreign('id_agent')
                ->references('id')
                ->on('agents')
                ->onDelete('cascade')
    			->onUpdate('no action');
        });



        // pour la table demande_flotes
        Schema::table('demande_flotes', function(Blueprint $table) {
			//migrations des clés
            $table->foreign('id_puce')
                ->references('id')
                ->on('puces')
                ->onDelete('cascade')
				->onUpdate('no action');

            $table->foreign('id_user')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('no action');

            $table->foreign('add_by')
                ->references('id')
                ->on('users')
				->onDelete('cascade')
                ->onUpdate('no action');

            $table->foreign('source')
                ->references('id')
                ->on('puces')
				->onDelete('cascade')
                ->onUpdate('no action');

        });



        // pour la table demande_destockages
        Schema::table('demande_destockages', function(Blueprint $table) {

			//migrations des clés
            $table->foreign('puce_source')
                ->references('id')
                ->on('puces')
                ->onDelete('cascade')
                ->onUpdate('no action');

            $table->foreign('puce_destination')
                ->references('id')
                ->on('puces')
                ->onDelete('cascade')
				->onUpdate('no action');

            $table->foreign('id_user')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('no action');

            $table->foreign('add_by')
                ->references('id')
                ->on('users')
				->onDelete('cascade')
				->onUpdate('no action');

        });



        // pour la table destockages
        Schema::table('destockages', function(Blueprint $table) {
			//migrations des clés

            $table->foreign('id_recouvreur')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
				->onUpdate('no action');
        });




        // pour la table retour_flotes
        Schema::table('retour_flotes', function(Blueprint $table) {
			//migrations des clés
            $table->foreign('id_approvisionnement')
                ->references('id')
                ->on('approvisionnements')
                ->onDelete('cascade')
				->onUpdate('no action');

            $table->foreign('id_user')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
				->onUpdate('no action');
        });


        // pour la table caisse
        Schema::table('caisses', function(Blueprint $table) {
			//migrations des clés
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
       //
    }
}

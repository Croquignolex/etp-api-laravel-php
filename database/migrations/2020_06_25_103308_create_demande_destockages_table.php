<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDemandeDestockagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('demande_destockages', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('add_by')->unsigned()->nullable()->index();
            $table->integer('id_user')->unsigned()->nullable()->index();
            $table->string('reference')->nullable();
            $table->double('montant')->unsigned()->default('0');
            $table->integer('reste')->unsigned()->default('0');
            $table->string('statut')->nullable();
            $table->integer('puce_source')->unsigned()->nullable()->index();
            $table->integer('puce_destination')->unsigned()->nullable()->index();	
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
        Schema::dropIfExists('demande_destockages');
    }
}

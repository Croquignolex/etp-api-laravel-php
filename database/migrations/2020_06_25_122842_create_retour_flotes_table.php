<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateRetourFlotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('retour_flotes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_user')->unsigned()->nullable()->index();
            $table->integer('id_approvisionnement')->unsigned()->nullable()->index();
            $table->string('reference')->nullable();
            $table->double('montant')->unsigned()->default('0');
            $table->integer('reste')->unsigned()->nullable()->default('0');
            $table->string('statut')->nullable();
            $table->integer('user_destination')->unsigned()->nullable()->index();
			$table->integer('user_source')->unsigned()->nullable()->index();         
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
        Schema::dropIfExists('retour_flotes');
    }
}

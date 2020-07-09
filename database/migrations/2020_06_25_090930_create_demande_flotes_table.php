<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDemandeFlotesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('demande_flotes', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('add_by')->unsigned()->nullable()->index();
            $table->integer('id_user')->unsigned()->nullable()->index();
            $table->string('reference')->nullable();
            $table->integer('montant')->unsigned()->default('0');
            $table->string('statut')->nullable();
            $table->integer('source')->unsigned()->nullable()->index();
            $table->integer('id_puce')->unsigned()->nullable()->index();			
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
        Schema::dropIfExists('demande_flotes');
    }
}

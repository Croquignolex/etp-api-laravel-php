<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateDestockagesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('destockages', function (Blueprint $table) {

            $table->increments('id');
            $table->integer('id_recouvreur')->unsigned()->nullable()->index();
            $table->string('reference')->nullable();
            $table->string('statut')->nullable();
            $table->string('note')->nullable();
            $table->integer('montant')->nullable();
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
        Schema::dropIfExists('destockages');
    }
}

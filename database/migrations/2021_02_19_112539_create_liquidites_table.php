<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateLiquiditesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('liquidites', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('id_user')->unsigned()->nullable()->index();
            $table->integer('id_reception')->unsigned()->nullable()->index();
            $table->double('montant')->nullable();
            $table->string('statut')->nullable();
            $table->text('note')->nullable();
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
        Schema::dropIfExists('liquidites');
    }
}

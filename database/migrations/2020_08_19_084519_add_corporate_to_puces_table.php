<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCorporateToPucesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('puces', function (Blueprint $table) {

            $table->integer('corporate')->unsigned()->nullable()->after('nom')->index();

            //migrations des clés
            $table->foreign('corporate')
                ->references('id')
                ->on('corporates')
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
        Schema::table('puces', function (Blueprint $table) {
            $table->dropColumn('corporate');
        });
    }
}

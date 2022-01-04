<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdAgencyToPucesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('puces', function (Blueprint $table) {
            $table->integer('id_agency')->unsigned()->nullable()->after('id_agent');

            $table->foreign('id_agency')
            ->references('id')
            ->on('agencies')
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
            $table->dropColumn('id_agency');
        });
    }
}

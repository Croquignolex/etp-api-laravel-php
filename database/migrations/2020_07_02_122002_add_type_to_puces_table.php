<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddTypeToPucesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('puces', function (Blueprint $table) {

            $table->integer('type')->unsigned()->nullable()->after('nom')->index();

            //migrations des clés
            $table->foreign('type')
                ->references('id')
                ->on('type_puces')
                ->onDelete('set null')
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
            $table->dropColumn('type');
        });
    }
}

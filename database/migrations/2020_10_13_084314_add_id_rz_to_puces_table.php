<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdRzToPucesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('puces', function (Blueprint $table) {
            $table->integer('id_rz')->unsigned()->nullable()->after('type');

            $table->foreign('id_rz')
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
        Schema::table('puces', function (Blueprint $table) {
            $table->dropColumn('puce_rz');
        });
    }
}

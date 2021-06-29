<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIdVendorToTreasuriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('treasuries', function (Blueprint $table) {
            $table->integer('id_vendor')
                ->unsigned()
                ->nullable()
                ->default(null)
                ->after('id_manager');

            $table->foreign('id_vendor')
                ->references('id')
                ->on('vendors')
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
        Schema::table('treasuries', function (Blueprint $table) {
            $table->dropForeign('id_vendor');
            $table->dropColumn('id_vendor');
        });
    }
}

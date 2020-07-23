<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRecuToRecouvrementsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('recouvrements', function (Blueprint $table) {
            $table->string('recu')->nullable()->after('montant');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('recouvrements', function (Blueprint $table) {
            $table->dropColumn('recu');
        });
    }
}

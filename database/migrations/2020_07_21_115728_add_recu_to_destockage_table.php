<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRecuToDestockageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('destockages', function (Blueprint $table) {
            $table->string('id_puce')->after('id_recouvreur');
            $table->string('id_agent')->nullable()->after('id_recouvreur');
            $table->string('fournisseur')->nullable()->after('id_recouvreur');
            $table->string('type')->after('id_recouvreur');
            $table->string('recu')->nullable()->after('id_recouvreur');

        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('destockages', function (Blueprint $table) {
            $table->dropColumn('id_puce');
            $table->dropColumn('fournisseur');
            $table->dropColumn('type');
            $table->dropColumn('recu');
        });
    }
}

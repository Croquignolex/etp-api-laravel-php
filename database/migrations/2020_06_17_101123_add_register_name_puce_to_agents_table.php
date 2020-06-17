<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddRegisterNamePuceToAgentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('puce_number')->nullable()->after('id')->default(null);
            $table->string('puce_name')->nullable()->after('id')->default(null);
            $table->string('point_de_vente')->nullable()->after('id')->default(null);
            $table->string('img_cni_back')->nullable()->after('img_cni')->default(null);
        });

    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('puce_number');
            $table->dropColumn('puce_name');
            $table->dropColumn('point_de_vente');
            $table->dropColumn('img_cni_back');
        });
    }
}

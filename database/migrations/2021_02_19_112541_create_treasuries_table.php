<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTreasuriesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('treasuries', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name');
            $table->double('amount');
            $table->string('reason');
            $table->string('type');
            $table->string('receipt')->nullable();
            $table->text('description')->nullable();
            $table->integer('id_manager')->unsigned();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_manager')
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
        Schema::dropIfExists('treasuries');
    }
}

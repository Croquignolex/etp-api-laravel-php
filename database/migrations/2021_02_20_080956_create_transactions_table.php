<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTransactionsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->increments('id');
            $table->string('type');
            $table->integer('id_operator')->unsigned();
            $table->integer('id_left')->unsigned()->nullable();
            $table->string('left')->nullable();
            $table->string('right')->nullable();
            $table->integer('id_right')->unsigned()->nullable();
            $table->double('in');
            $table->double('out');
            $table->double('balance');
            $table->integer('id_user')->unsigned();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('id_operator')
                ->references('id')
                ->on('flotes')
                ->onDelete('cascade')
                ->onUpdate('no action');

            $table->foreign('id_user')
                ->references('id')
                ->on('users')
                ->onDelete('cascade')
                ->onUpdate('no action');

            $table->foreign('id_left')
                ->references('id')
                ->on('puces')
                ->onDelete('cascade')
                ->onUpdate('no action');

            $table->foreign('id_right')
                ->references('id')
                ->on('puces')
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
        Schema::dropIfExists('transactions');
    }
}

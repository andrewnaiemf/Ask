<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class MakeTimeDayMonthNullableInBookingsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->time('time')->nullable()->change();
            $table->string('day')->nullable()->change();
            $table->string('month')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('bookings', function (Blueprint $table) {
            $table->time('time')->nullable(false)->change();
            $table->string('day')->nullable(false)->change();
            $table->string('month')->nullable(false)->change();
        });
    }
}

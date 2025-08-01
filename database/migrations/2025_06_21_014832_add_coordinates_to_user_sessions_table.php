<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->decimal('latitude', 10, 7)->nullable()->after('location_city');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
        });
    }

    public function down()
    {
        Schema::table('user_sessions', function (Blueprint $table) {
            $table->dropColumn(['latitude', 'longitude']);
        });
    }

};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('real_state_projects', function (Blueprint $table) {
            $table->integer('order')->default(0)->after('name');
            $table->boolean('active')->default(true)->after('order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('real_state_projects', function (Blueprint $table) {
            $table->dropColumn('order');
            $table->dropColumn('active');
        });
    }
};

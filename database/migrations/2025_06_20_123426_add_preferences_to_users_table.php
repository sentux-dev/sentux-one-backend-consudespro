<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('language', 5)->default('es')->after('email');
            $table->string('date_format', 20)->default('dd/MM/yyyy')->after('language');
            $table->string('number_format', 20)->default('european')->after('date_format');
            $table->string('timezone', 50)->default('America/Costa_Rica')->after('number_format');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['language', 'date_format', 'number_format', 'timezone']);
        });
    }
};

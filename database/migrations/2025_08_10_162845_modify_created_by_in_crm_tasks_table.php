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
        Schema::table('crm_tasks', function (Blueprint $table) {
            // Hacemos la columna 'created_by' nulable
            $table->foreignId('created_by')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            // Revertimos el cambio si es necesario
            $table->foreignId('created_by')->nullable(false)->change();
        });
    }
};
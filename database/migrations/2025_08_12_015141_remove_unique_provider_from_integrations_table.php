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
        Schema::table('integrations', function (Blueprint $table) {
            // Elimina la restricción de unicidad de la columna 'provider'
            // El nombre 'integrations_provider_unique' es el que Laravel genera por defecto.
            $table->dropUnique('integrations_provider_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('integrations', function (Blueprint $table) {
            // Vuelve a añadir la restricción si necesitas revertir la migración
            $table->unique('provider');
        });
    }
};
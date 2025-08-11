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
        Schema::table('crm_deal_custom_fields', function (Blueprint $table) {
            // ✅ Añadimos la columna 'active'
            // Es un booleano, no puede ser nulo, y por defecto será 'true' (activo)
            $table->boolean('active')->default(true)->after('required');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_deal_custom_fields', function (Blueprint $table) {
            // ✅ Esto permite revertir el cambio si es necesario
            $table->dropColumn('active');
        });
    }
};
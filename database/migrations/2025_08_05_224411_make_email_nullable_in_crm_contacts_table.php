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
        Schema::table('crm_contacts', function (Blueprint $table) {
            // 🔹 CORRECCIÓN: Modificamos la columna para que sea nulable,
            // sin intentar volver a añadir el índice 'unique' que ya existe.
            $table->string('email')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_contacts', function (Blueprint $table) {
            // La lógica para revertir se mantiene, pero hay que tener en cuenta
            // que fallará si ya se han insertado contactos con email nulo.
            $table->string('email')->nullable(false)->change();
        });
    }
};
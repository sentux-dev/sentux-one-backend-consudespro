<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Este método se ejecuta al correr 'php artisan migrate'.
     * Añade la columna 'preferences' a la tabla 'users'.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Añadimos la columna 'preferences' de tipo JSON.
            // Es 'nullable' porque un usuario nuevo podría no tener preferencias guardadas.
            // La colocamos después de la columna 'password' por orden.
            $table->json('theme_preferences')->nullable()->after('password');
        });
    }

    /**
     * Reverse the migrations.
     *
     * Este método se ejecuta si necesitas revertir la migración.
     * Elimina la columna 'preferences'.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('theme_preferences');
        });
    }
};
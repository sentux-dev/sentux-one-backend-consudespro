<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            // Este campo guardará el Message-ID del correo para evitar duplicados.
            // Es opcional (nullable) y se le añade un índice para búsquedas rápidas.
            $table->string('external_message_id')->nullable()->index()->after('email_log_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropColumn('external_message_id');
        });
    }
};
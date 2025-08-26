<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            // Añade una clave foránea opcional a la tabla de logs de email.
            // Es opcional porque solo las actividades de tipo 'correo' la tendrán.
            $table->foreignId('email_log_id')->nullable()->constrained('marketing_email_logs')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropForeign(['email_log_id']);
            $table->dropColumn('email_log_id');
        });
    }
};
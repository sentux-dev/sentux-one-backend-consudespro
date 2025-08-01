<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            // ✅ Relación con el contacto (obligatoria si es una tarea de CRM)
            $table->foreignId('contact_id')
                  ->nullable()
                  ->after('id')
                  ->constrained('crm_contacts')
                  ->onDelete('cascade');

            // ✅ Propietario de la tarea (puede ser diferente del creador)
            $table->foreignId('owner_id')
                  ->nullable()
                  ->after('contact_id')
                  ->constrained('users')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('contact_id');
            $table->dropConstrainedForeignId('owner_id');
        });
    }
};
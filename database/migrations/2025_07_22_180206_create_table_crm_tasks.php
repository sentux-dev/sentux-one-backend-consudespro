<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_tasks', function (Blueprint $table) {
            $table->id();

            // ✅ activity_id puede ser nulo
            $table->foreignId('activity_id')
                  ->nullable()
                  ->constrained('crm_activities')
                  ->onDelete('cascade');

            $table->string('description')->nullable(); // ✅ Descripción específica para la tarea
            $table->enum('status', ['pendiente', 'completada', 'vencida'])->default('pendiente');
            $table->timestamp('schedule_date')->nullable();   // Fecha programada
            $table->timestamp('remember_date')->nullable();   // Recordatorio
            $table->string('action_type')->nullable();        // Tipo de acción (llamada, correo, etc.)

            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_tasks');
    }
};
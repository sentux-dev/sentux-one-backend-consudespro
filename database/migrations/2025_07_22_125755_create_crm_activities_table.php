<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')
                  ->constrained('crm_contacts')
                  ->onDelete('cascade');

            $table->string('type'); // llamada, correo, tarea, reunión, nota, etc.
            $table->string('title');
            $table->text('description')->nullable();

            // Campos adicionales según el tipo
            $table->string('call_result')->nullable(); // Contestada, No contestada...
            $table->string('task_action_type')->nullable(); // nota, correo, llamada...
            $table->timestamp('schedule_date')->nullable(); // programada
            $table->timestamp('remember_date')->nullable(); // recordatorio
            $table->string('meeting_title')->nullable();

            $table->timestamps(); // created_at = fecha de creación de la actividad
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_activities');
    }
};
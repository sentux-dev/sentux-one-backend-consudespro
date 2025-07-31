<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_workflow_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('crm_workflows')->onDelete('cascade');
            $table->string('action_type'); // 'create_contact', 'create_task', 'notify_user'
            $table->json('parameters'); // Parámetros de la acción (ej: 'user_id' a notificar, 'task_description')
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_workflow_actions');
    }
};
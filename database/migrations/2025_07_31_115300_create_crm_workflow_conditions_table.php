<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_workflow_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('crm_workflows')->onDelete('cascade');
            $table->string('field'); // Campo del payload del lead (ej: 'utm_source', 'country')
            $table->string('operator'); // 'equals', 'contains', 'starts_with', etc.
            $table->string('value'); // El valor a comparar
            $table->string('group')->nullable(); // Para agrupar condiciones (ej: 'group_1')
            $table->enum('type', ['AND', 'OR'])->default('AND');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_workflow_conditions');
    }
};
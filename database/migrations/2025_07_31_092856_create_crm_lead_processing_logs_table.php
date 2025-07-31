<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('crm_lead_processing_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('external_lead_id')->constrained('crm_external_leads')->onDelete('cascade');
            $table->unsignedBigInteger('workflow_id')->nullable();
            $table->string('action_taken');
            $table->text('details')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('crm_lead_processing_logs');
    }
};
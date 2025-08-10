<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('crm_contact_sequence_enrollments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('crm_contacts')->onDelete('cascade');
            $table->foreignId('sequence_id')->constrained('crm_sequences')->onDelete('cascade');
            $table->timestamp('enrolled_at');
            $table->enum('status', ['active', 'paused', 'completed', 'stopped'])->default('active');
            $table->integer('current_step')->default(0);
            $table->timestamp('next_step_due_at')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('crm_contact_sequence_enrollments');
    }
};
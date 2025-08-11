<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('crm_sequence_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sequence_id')->constrained('crm_sequences')->onDelete('cascade');
            $table->integer('order')->default(0);
            $table->integer('delay_amount');
            $table->enum('delay_unit', ['minutes', 'hours', 'days']);
            $table->enum('action_type', ['send_email_template', 'create_manual_task']);
            $table->json('parameters');
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('crm_sequence_steps');
    }
};
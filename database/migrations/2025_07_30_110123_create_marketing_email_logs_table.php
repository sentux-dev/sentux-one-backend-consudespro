<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('marketing_email_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('marketing_campaigns')->onDelete('cascade');
            $table->foreignId('contact_id')->constrained('crm_contacts')->onDelete('cascade');
            $table->string('provider_message_id')->nullable()->index(); // ID de Mandrill, Brevo, etc.
            $table->enum('status', ['enviado', 'entregado', 'abierto', 'clic', 'rebotado', 'spam', 'fallido'])->default('enviado');
            $table->text('error_message')->nullable(); // Para guardar el motivo del fallo
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('clicked_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('marketing_email_logs');
    }
};
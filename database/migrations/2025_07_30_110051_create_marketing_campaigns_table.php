<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('marketing_campaigns', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subject');
            $table->string('from_name');
            $table->string('from_email');
            $table->longText('content_html')->nullable();
            $table->string('template_id')->nullable(); // Para plantillas de proveedores como Mandrill
            $table->enum('status', ['borrador', 'programada', 'enviando', 'enviada', 'error'])->default('borrador');
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            
            // Campos para Pruebas A/B
            $table->foreignId('parent_campaign_id')->nullable()->constrained('marketing_campaigns')->onDelete('cascade');
            $table->string('variant', 1)->nullable(); // 'A', 'B'
            $table->boolean('is_test')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('marketing_campaigns');
    }
};
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('crm_external_leads', function (Blueprint $table) {
            $table->id();
            $table->string('source')->index();
            $table->json('payload');
            $table->enum('status', ['pendiente', 'procesado', 'error'])->default('pendiente')->index();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('crm_external_leads');
    }
};
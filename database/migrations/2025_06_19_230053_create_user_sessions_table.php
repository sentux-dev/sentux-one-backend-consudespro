<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('user_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('token_id')->nullable(); // Relación con personal_access_tokens, opcional

            // Información del cliente
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Información de ubicación (estimada por IP)
            $table->string('location_country')->nullable();
            $table->string('location_region')->nullable();
            $table->string('location_city')->nullable();

            // Información del dispositivo
            $table->string('device_type')->nullable();         // Ej: desktop, phone, tablet
            $table->string('platform')->nullable();            // Ej: Windows, iOS, Android
            $table->string('browser')->nullable();             // Ej: Chrome, Safari
            $table->string('browser_version')->nullable();

            $table->boolean('is_mobile')->default(false);
            $table->boolean('is_desktop')->default(true);

            $table->timestamp('last_activity_at')->nullable();
            $table->timestamp('revoked_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_sessions');
    }
};
// This migration creates a user_sessions table to track user sessions with detailed information about the client, device, and location.

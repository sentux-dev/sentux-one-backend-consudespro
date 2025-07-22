<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable(); // quién ejecutó la acción
            $table->string('action'); // Ej: "update_contact", "delete_deal"
            $table->string('entity_type'); // Ej: "Contact", "Deal", "User", "Config"
            $table->unsignedBigInteger('entity_id')->nullable(); // id de la entidad
            $table->json('changes')->nullable(); // antes/después si aplica
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logs');
    }
};

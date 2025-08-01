<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 🔹 Eliminar la tabla si ya existe
        Schema::dropIfExists('crm_contact_custom_field_values');

        // 🔹 Crear la tabla con la nueva estructura
        Schema::create('crm_contact_custom_field_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id');
            $table->unsignedBigInteger('custom_field_id');
            $table->text('value')->nullable();
            $table->timestamps();

            // 🔹 Llaves foráneas
            $table->foreign('contact_id')
                  ->references('id')
                  ->on('crm_contacts')
                  ->onDelete('cascade');

            $table->foreign('custom_field_id')
                  ->references('id')
                  ->on('crm_contact_custom_fields')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contact_custom_field_values');
    }
};
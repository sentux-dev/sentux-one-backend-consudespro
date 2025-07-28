<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
       Schema::create('crm_contact_associations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('contact_id'); // Contacto principal
            $table->unsignedBigInteger('associated_contact_id'); // Contacto, negocio, empresa, etc.
            $table->string('association_type'); // Tipo de asociación: contacts, deals, companies, etc.
            $table->string('relation_type')->nullable(); // Relación específica: Hermano, Proveedor, etc.
            $table->timestamps();

            $table->foreign('contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->onDelete('cascade');

            $table->foreign('associated_contact_id')
                ->references('id')
                ->on('crm_contacts')
                ->onDelete('cascade'); // 🔹 Por ahora solo contactos, luego adaptamos para otros módulos
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contact_associations');
    }
};

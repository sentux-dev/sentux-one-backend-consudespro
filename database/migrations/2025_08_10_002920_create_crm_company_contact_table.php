<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El nombre de la tabla sigue la convención de Laravel: modelos en singular y orden alfabético.
        Schema::create('crm_company_contact', function (Blueprint $table) {
            $table->id();
            
            $table->foreignId('company_id')->constrained('crm_companies')->onDelete('cascade');
            $table->foreignId('contact_id')->constrained('crm_contacts')->onDelete('cascade');

            // Podríamos añadir un relation_type aquí si fuera necesario en el futuro
            // $table->string('relation_type')->nullable();

            $table->timestamps();
            
            // Asegura que una empresa solo se pueda asociar una vez a un contacto
            $table->unique(['company_id', 'contact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_company_contact');
    }
};
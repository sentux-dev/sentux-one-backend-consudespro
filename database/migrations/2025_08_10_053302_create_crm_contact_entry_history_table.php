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
        Schema::create('crm_contact_entry_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_id')->constrained('crm_contacts')->onDelete('cascade');
            
            // La fecha y hora exactas del ingreso. Esta es la fecha que usarás para tus reportes.
            $table->timestamp('entry_at')->useCurrent();
            
            // Origen del ingreso (de dónde vino el lead)
            $table->foreignId('origin_id')->nullable()->constrained('crm_origins')->onDelete('set null');
            
            // Campaña asociada al ingreso
            $table->foreignId('campaign_id')->nullable()->constrained('crm_campaigns')->onDelete('set null');
            
            // El ID del lead original que generó este ingreso
            $table->foreignId('external_lead_id')->nullable()->constrained('crm_external_leads')->onDelete('set null');
            
            // Un campo JSON para guardar el payload original u otros detalles relevantes
            $table->json('details')->nullable();
            
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_contact_entry_history');
    }
};
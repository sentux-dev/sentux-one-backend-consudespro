<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_lead_sources', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nombre para identificar la fuente (ej: "Facebook Ads - Campaña Verano")
            $table->string('source_key')->unique(); // El identificador que se usará en la URL (ej: 'facebook_verano')
            $table->string('api_key', 64)->unique(); // La API Key secreta
            $table->json('allowed_domains')->nullable(); // Array de dominios permitidos (ej: ["graph.facebook.com"])
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_lead_sources');
    }
};
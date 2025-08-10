<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Este método se ejecuta al correr 'php artisan migrate'.
     * Elimina la tabla obsoleta.
     */
    public function up(): void
    {
        Schema::dropIfExists('crm_contact_crm_deal');
    }

    /**
     * Reverse the migrations.
     * Este método se ejecuta si necesitas revertir la migración.
     * Vuelve a crear la tabla con su estructura original.
     */
    public function down(): void
    {
        Schema::create('crm_contact_crm_deal', function (Blueprint $table) {
            $table->id();
            $table->foreignId('crm_contact_id')->constrained('crm_contacts')->onDelete('cascade');
            $table->foreignId('crm_deal_id')->constrained('crm_deals')->onDelete('cascade');
            $table->timestamps();
        });
    }
};
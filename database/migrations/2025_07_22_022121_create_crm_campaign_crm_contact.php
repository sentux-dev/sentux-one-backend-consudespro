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
        Schema::create('crm_campaign_crm_contact', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('crm_contact_id');
            $table->unsignedBigInteger('crm_campaign_id');
            $table->boolean('is_original')->default(false);
            $table->boolean('is_last')->default(false);
            $table->timestamps();

            $table->foreign('crm_contact_id')->references('id')->on('crm_contacts')->onDelete('cascade');
            $table->foreign('crm_campaign_id')->references('id')->on('crm_campaigns')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_campaign_crm_contact');
    }
};

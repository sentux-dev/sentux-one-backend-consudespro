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
        Schema::create('crm_contacts', function (Blueprint $table) {
            $table->id();
            $table->string('first_name');
            $table->string('last_name')->nullable();
            $table->string('cellphone')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->unique();
            $table->unsignedBigInteger('contact_status_id');
            $table->unsignedBigInteger('disqualification_reason_id')->nullable();
            $table->unsignedBigInteger('owner_id')->nullable(); // usuario propietario
            $table->string('occupation')->nullable();
            $table->date('birthdate')->nullable();
            $table->string('address')->nullable();
            $table->string('country')->nullable();
            $table->boolean('active')->default(true);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('contact_status_id')->references('id')->on('crm_contact_statuses');
            $table->foreign('disqualification_reason_id')->references('id')->on('crm_disqualification_reasons');
            $table->foreign('owner_id')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_contacts');
    }
};

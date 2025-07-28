<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('industry')->nullable();           // Industria (opcional)
            $table->string('website')->nullable();            // Sitio web
            $table->string('phone')->nullable();              // Teléfono principal
            $table->string('email')->nullable();              // Email general
            $table->string('country')->nullable();            // País
            $table->string('address')->nullable();            // Dirección
            $table->unsignedBigInteger('owner_id')->nullable(); // Usuario propietario

            $table->timestamps();
            $table->softDeletes();

            $table->foreign('owner_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_companies');
    }
};
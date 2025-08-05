<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->unique(); // ej: 'mandrill', 'facebook_ads'
            $table->string('name'); // ej: 'Mandrill (Marketing)'
            $table->text('credentials')->nullable(); // Guardará el JSON encriptado
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('integrations');
    }
};
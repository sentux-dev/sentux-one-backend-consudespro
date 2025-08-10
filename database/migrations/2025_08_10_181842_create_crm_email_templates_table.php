<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('crm_email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nombre interno para identificar la plantilla
            $table->string('subject'); // Asunto del correo
            $table->longText('body'); // Contenido del correo (HTML)
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('crm_email_templates');
    }
};
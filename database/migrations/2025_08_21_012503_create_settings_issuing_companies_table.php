<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('settings_issuing_companies', function (Blueprint $table) {
            $table->id();
            $table->string('name'); // Nombre comercial
            $table->string('legal_name'); // Razón social
            $table->string('tax_id'); // Cédula Jurídica / DNI
            $table->text('address')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->string('logo_url')->nullable();
            $table->json('bank_accounts')->nullable();
            $table->text('pdf_header_text')->nullable();
            $table->text('pdf_footer_text')->nullable();
            $table->text('default_notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('settings_issuing_companies');
    }
};
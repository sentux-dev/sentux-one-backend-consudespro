<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issuing_company_id')->constrained('settings_issuing_companies');
            $table->foreignId('contact_id')->constrained('crm_contacts');
            $table->foreignId('user_id')->comment('Vendedor')->constrained('users');
            $table->string('quote_number')->unique();
            $table->enum('status', ['borrador', 'enviada', 'aceptada', 'rechazada', 'expirada']);
            $table->date('valid_until')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->enum('discount_type', ['percentage', 'fixed'])->nullable();
            $table->decimal('discount_value', 10, 2)->nullable();
            $table->json('tax_details')->nullable();
            $table->decimal('grand_total', 10, 2)->default(0);
            $table->text('notes_customer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sales_quotes');
    }
};
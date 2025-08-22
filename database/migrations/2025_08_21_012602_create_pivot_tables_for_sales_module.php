<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // Impuestos predefinidos de un producto
        Schema::create('sales_product_tax', function (Blueprint $table) {
            $table->foreignId('product_id')->constrained('sales_products')->onDelete('cascade');
            $table->foreignId('tax_id')->constrained('settings_taxes')->onDelete('cascade');
            $table->primary(['product_id', 'tax_id']);
        });

        // Cargos aplicados a una cotización
        Schema::create('sales_fee_quote', function (Blueprint $table) {
            $table->foreignId('quote_id')->constrained('sales_quotes')->onDelete('cascade');
            $table->foreignId('fee_id')->constrained('settings_fees')->onDelete('cascade');
            $table->primary(['quote_id', 'fee_id']);
        });
    }

    public function down(): void {
        Schema::dropIfExists('sales_product_tax');
        Schema::dropIfExists('sales_fee_quote');
    }
};
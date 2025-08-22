<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sales_products', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('sku')->unique()->nullable(); // Código interno
            $table->string('tax_code')->nullable(); // Código fiscal externo
            $table->text('description')->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->boolean('price_includes_tax')->default(false);
            $table->boolean('is_exempt')->default(false);
            $table->boolean('track_inventory')->default(false);
            $table->decimal('stock_quantity', 10, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('sales_products');
    }
};
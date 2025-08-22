<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('sales_products');
            $table->decimal('quantity_change', 10, 2);
            $table->string('reason');
            $table->morphs('sourceable'); // Para enlazar a Quote, PurchaseOrder, etc.
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('inventory_movements');
    }
};
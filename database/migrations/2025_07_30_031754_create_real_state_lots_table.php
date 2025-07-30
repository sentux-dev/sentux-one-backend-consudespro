<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('real_state_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('real_state_projects')->onDelete('cascade');
            $table->string('lot_number');
            $table->string('slug')->unique();
            $table->foreignId('house_model_id')->nullable()->constrained('real_state_house_models')->onDelete('set null');
            $table->foreignId('seller_id')->nullable()->constrained('users')->onDelete('set null');
            $table->foreignId('formalizer_id')->nullable()->constrained('users')->onDelete('set null');
            $table->decimal('base_price', 15, 2)->default(0);
            $table->decimal('size', 10, 2);
            $table->decimal('extra_footage', 10, 2)->default(0);
            $table->decimal('extra_footage_cost', 15, 2)->default(0);
            $table->decimal('down_payment_percentage', 5, 2)->default(0);
            $table->date('reservation_date')->nullable();
            $table->date('delivery_date')->nullable();
            $table->date('contract_signing_date')->nullable();
            $table->date('contract_due_date')->nullable();
            $table->date('house_delivery_date')->nullable();
            $table->string('status')->default('Disponible'); // Disponible, Reservado, Vendido
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void {
        Schema::dropIfExists('real_state_lots');
    }
};
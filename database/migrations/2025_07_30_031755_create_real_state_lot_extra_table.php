<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('real_state_lot_extra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained('real_state_lots')->onDelete('cascade');
            $table->foreignId('extra_id')->constrained('real_state_extras')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('real_state_lot_extra');
    }
};
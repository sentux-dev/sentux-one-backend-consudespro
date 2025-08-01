<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('marketing_segments', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('filters'); // Guarda los criterios de filtrado en formato JSON
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('marketing_segments');
    }
};
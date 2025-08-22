<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('settings_taxes', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('rate', 5, 2); // Ej: 13.00
            $table->enum('type', ['iva', 'retencion', 'percepcion', 'otro']);
            $table->enum('calculation_type', ['percentage', 'fixed']);
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('settings_taxes');
    }
};
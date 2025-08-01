<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('real_state_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lot_id')->constrained('real_state_lots')->onDelete('cascade');
            $table->string('name');
            $table->string('file_path');
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('real_state_documents');
    }
};
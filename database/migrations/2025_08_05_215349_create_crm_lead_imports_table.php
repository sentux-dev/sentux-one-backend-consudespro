<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_lead_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('original_file_name');
            $table->string('status')->default('processing'); // processing, completed, failed
            $table->integer('total_rows')->default(0);
            $table->integer('imported_count')->default(0);
            $table->json('mappings');
            $table->timestamps();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('crm_lead_imports');
    }
};
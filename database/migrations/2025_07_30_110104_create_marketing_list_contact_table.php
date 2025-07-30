<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('marketing_list_contact', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mailing_list_id')->constrained('marketing_mailing_lists')->onDelete('cascade');
            $table->foreignId('contact_id')->constrained('crm_contacts')->onDelete('cascade');
            $table->timestamps();
        });
    }

    public function down(): void {
        Schema::dropIfExists('marketing_list_contact');
    }
};
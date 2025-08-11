<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('crm_contact_entry_history', function (Blueprint $table) {
            $table->boolean('is_original')->default(false)->after('details');
            $table->boolean('is_last')->default(false)->after('is_original');
        });
    }
    public function down(): void {
        Schema::table('crm_contact_entry_history', function (Blueprint $table) {
            $table->dropColumn(['is_original', 'is_last']);
        });
    }
};
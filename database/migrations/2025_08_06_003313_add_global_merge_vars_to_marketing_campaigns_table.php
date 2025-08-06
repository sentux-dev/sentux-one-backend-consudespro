<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->json('global_merge_vars')->nullable()->after('template_id');
        });
    }
    public function down(): void {
        Schema::table('marketing_campaigns', function (Blueprint $table) {
            $table->dropColumn('global_merge_vars');
        });
    }
};
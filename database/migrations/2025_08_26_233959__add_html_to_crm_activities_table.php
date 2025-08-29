<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->longText('html_description')->nullable()->after('description');
            $table->boolean('has_inline_images')->default(false)->after('html_description');
        });
    }
    public function down(): void {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropColumn(['html_description', 'has_inline_images']);
        });
    }
};

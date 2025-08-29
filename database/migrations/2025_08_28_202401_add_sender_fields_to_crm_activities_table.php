<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->string('sender_email')->nullable()->after('updated_by');
            $table->string('sender_name')->nullable()->after('sender_email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropColumn(['sender_email', 'sender_name']);
        });
    }
};

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
        Schema::table('user_email_accounts', function (Blueprint $table) {
            if (!Schema::hasColumn('user_email_accounts', 'last_uidvalidity')) {
                $table->string('last_uidvalidity', 64)->nullable()->after('last_sync_uid');
                $table->index('last_uidvalidity', 'user_email_accounts_uidvalidity_idx');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_email_accounts', function (Blueprint $table) {
            if (Schema::hasColumn('user_email_accounts', 'last_uidvalidity')) {
                $table->dropIndex('user_email_accounts_uidvalidity_idx');
                $table->dropColumn('last_uidvalidity');
            }
        });
    }
};

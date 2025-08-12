<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('integrations', function (Blueprint $table) {
            // Guardará un JSON con pares de form_id => last_cursor
            $table->json('sync_cursors')->nullable()->after('credentials');
        });
    }
    public function down(): void {
        Schema::table('integrations', function (Blueprint $table) {
            $table->dropColumn('sync_cursors');
        });
    }
};
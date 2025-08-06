<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_external_leads', function (Blueprint $table) {
            $table->foreignId('lead_import_id')->nullable()->after('id')
                  ->constrained('crm_lead_imports')
                  ->onDelete('cascade'); // Si se borra el lote, se borran los leads
        });
    }
    public function down(): void
    {
        Schema::table('crm_external_leads', function (Blueprint $table) {
            $table->dropForeign(['lead_import_id']);
            $table->dropColumn('lead_import_id');
        });
    }
};
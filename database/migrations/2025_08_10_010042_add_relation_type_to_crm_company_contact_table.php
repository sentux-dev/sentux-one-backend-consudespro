<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_company_contact', function (Blueprint $table) {
            $table->string('relation_type')->nullable()->after('contact_id');
        });
    }

    public function down(): void
    {
        Schema::table('crm_company_contact', function (Blueprint $table) {
            $table->dropColumn('relation_type');
        });
    }
};
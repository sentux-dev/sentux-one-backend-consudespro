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
        Schema::table('crm_contact_custom_fields', function (Blueprint $table) {
            // 🔹 Elimina las columnas antiguas si existen
            if (Schema::hasColumn('crm_contact_custom_fields', 'contact_id')) {
                $table->dropForeign(['contact_id']);
                $table->dropColumn(['contact_id', 'field_key', 'field_value']);
            }

            // 🔹 Nuevas columnas (solo si no existen)
            if (!Schema::hasColumn('crm_contact_custom_fields', 'name')) {
                $table->string('name')->after('id');
            }
            if (!Schema::hasColumn('crm_contact_custom_fields', 'label')) {
                $table->string('label')->after('name');
            }
            if (!Schema::hasColumn('crm_contact_custom_fields', 'type')) {
                $table->enum('type', ['text', 'number', 'select', 'date'])->after('label');
            }
            if (!Schema::hasColumn('crm_contact_custom_fields', 'options')) {
                $table->json('options')->nullable()->after('type');
            }
            if (!Schema::hasColumn('crm_contact_custom_fields', 'active')) {
                $table->boolean('active')->default(true)->after('options');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_contact_custom_fields', function (Blueprint $table) {
            // 🔹 Revertir a la versión antigua si fuese necesario
            $table->dropColumn(['name', 'label', 'type', 'options', 'active']);

            $table->unsignedBigInteger('contact_id')->nullable();
            $table->string('field_key')->nullable();
            $table->text('field_value')->nullable();

            $table->foreign('contact_id')->references('id')->on('crm_contacts')->onDelete('cascade');
        });
    }
};
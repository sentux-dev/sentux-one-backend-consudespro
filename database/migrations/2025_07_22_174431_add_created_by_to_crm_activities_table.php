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
            $table->foreignId('created_by')
                ->before('created_at') // Aseguramos que el campo esté después de contact_id
                ->nullable()
                ->constrained('users')
                ->nullOnDelete(); // Si se borra el usuario, el campo se pone NULL en vez de romper la relación

            $table->foreignId('updated_by')
                ->after('created_by') // Aseguramos que el campo esté después de created_by
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');

            $table->dropForeign(['updated_by']);
            $table->dropColumn('updated_by');
        });
    }
};

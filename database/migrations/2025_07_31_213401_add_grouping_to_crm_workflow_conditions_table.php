<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('crm_workflow_conditions', function (Blueprint $table) {
            // Renombramos 'group' a 'group_identifier' para evitar conflictos con palabras reservadas de SQL.
            // Le damos un nombre más descriptivo.
            $table->renameColumn('group', 'group_identifier');
            
            // Renombramos 'type' a 'group_logic' por la misma razón.
            $table->renameColumn('type', 'group_logic');
        });
    }

    public function down(): void
    {
        Schema::table('crm_workflow_conditions', function (Blueprint $table) {
            $table->renameColumn('group_identifier', 'group');
            $table->renameColumn('group_logic', 'type');
        });
    }
};
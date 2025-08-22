<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings_issuing_companies', function (Blueprint $table) {
            // Verifica si la columna 'logo_url' existe antes de intentar renombrarla
            if (Schema::hasColumn('settings_issuing_companies', 'logo_url')) {
                $table->renameColumn('logo_url', 'logo_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('settings_issuing_companies', function (Blueprint $table) {
            if (Schema::hasColumn('settings_issuing_companies', 'logo_path')) {
                $table->renameColumn('logo_path', 'logo_url');
            }
        });
    }
};
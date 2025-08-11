<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            // Esta columna guardará la fecha y hora en que se envió la notificación
            $table->timestamp('reminder_sent_at')->nullable()->after('remember_date');
        });
    }

    public function down(): void
    {
        Schema::table('crm_tasks', function (Blueprint $table) {
            $table->dropColumn('reminder_sent_at');
        });
    }
};
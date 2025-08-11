<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('crm_activities', function (Blueprint $table) {
            // Columna para saber si se debe enviar una invitación para la reunión
            $table->boolean('send_invitation')->default(false)->after('meeting_title');
        });
    }
    public function down(): void {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropColumn('send_invitation');
        });
    }
};
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('marketing_email_logs', function (Blueprint $table) {
            // Cambiamos de ENUM a VARCHAR(50)
            $table->string('status', 50)->default('enviado')->change();
        });
    }

    public function down(): void
    {
        Schema::table('marketing_email_logs', function (Blueprint $table) {
            // Restaurar ENUM original (por si haces rollback)
            $table->enum('status', [
                'enviado',
                'entregado',
                'abierto',
                'clic',
                'rebotado',
                'spam',
                'fallido'
            ])->default('enviado')->change();
        });
    }
};

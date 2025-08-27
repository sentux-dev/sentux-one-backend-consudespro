// database/migrations/YYYY_MM_DD_HHMMSS_create_user_email_accounts_table.php

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_email_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            
            $table->string('email_address');
            $table->enum('connection_type', ['generic_imap', 'google_oauth', 'microsoft_oauth']);

            // Credenciales Salientes (SMTP)
            $table->string('smtp_host');
            $table->integer('smtp_port');
            $table->string('smtp_encryption', 10);
            $table->string('smtp_username');

            // Credenciales Entrantes (IMAP)
            $table->string('imap_host');
            $table->integer('imap_port');
            $table->string('imap_encryption', 10);
            $table->string('imap_username');

            // Contraseña (será encriptada, por eso usamos TEXT)
            $table->text('password');

            // Estado y Sincronización
            $table->boolean('is_active')->default(false);
            $table->timestamp('last_sync_at')->nullable();
            $table->text('sync_error_message')->nullable();
            
            // Para optimización (sincronización incremental)
            $table->string('last_sync_uid')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_email_accounts');
    }
};
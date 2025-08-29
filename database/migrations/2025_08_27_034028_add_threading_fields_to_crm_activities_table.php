<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            
            // Guarda los destinatarios originales (To, CC) para la función "Responder a Todos".
            $table->json('original_recipients')->nullable()->after('description');
            
            // Guarda los IDs de los correos a los que se responde para mantener el hilo.
            $table->string('in_reply_to')->nullable()->after('external_message_id');
            $table->text('references')->nullable()->after('in_reply_to');
            // Hilo
            if (!Schema::hasColumn('crm_activities', 'thread_root_message_id')) {
                $table->string('thread_root_message_id')->nullable()->after('references');
            }
            if (!Schema::hasColumn('crm_activities', 'parent_activity_id')) {
                $table->unsignedBigInteger('parent_activity_id')->nullable()->after('thread_root_message_id');
                $table->index('parent_activity_id', 'crm_activities_parent_idx');
                $table->foreign('parent_activity_id')
                    ->references('id')->on('crm_activities')
                    ->onDelete('set null');
            }

            // Destinatarios (usa json; si tu MySQL no soporta JSON, cambia a text)
            if (!Schema::hasColumn('crm_activities', 'email_to')) {
                $table->json('email_to')->nullable()->after('html_description');
            }
            if (!Schema::hasColumn('crm_activities', 'email_cc')) {
                $table->json('email_cc')->nullable()->after('email_to');
            }
            if (!Schema::hasColumn('crm_activities', 'email_bcc')) {
                $table->json('email_bcc')->nullable()->after('email_cc');
            }
        });
    }

    public function down(): void
    {
        Schema::table('crm_activities', function (Blueprint $table) {
            $table->dropColumn(['external_message_id', 'original_recipients', 'in_reply_to', 'references']);
             if (Schema::hasColumn('crm_activities', 'parent_activity_id')) {
                $table->dropForeign(['parent_activity_id']);
                $table->dropIndex('crm_activities_parent_idx');
                $table->dropColumn('parent_activity_id');
            }
            if (Schema::hasColumn('crm_activities', 'thread_root_message_id')) {
                $table->dropColumn('thread_root_message_id');
            }
            if (Schema::hasColumn('crm_activities', 'email_to')) {
                $table->dropColumn('email_to');
            }
            if (Schema::hasColumn('crm_activities', 'email_cc')) {
                $table->dropColumn('email_cc');
            }
            if (Schema::hasColumn('crm_activities', 'email_bcc')) {
                $table->dropColumn('email_bcc');
            }
        });
    }
};
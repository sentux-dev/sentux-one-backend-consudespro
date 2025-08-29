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
        Schema::create('crm_activity_attachments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('activity_id');
            $table->string('filename');
            $table->string('mime_type', 190)->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('path'); // storage/app/public/...
            $table->boolean('is_inline')->default(false);
            $table->string('content_id')->nullable(); // para relacionar CIDs si quisieras
            $table->timestamps();

            $table->foreign('activity_id')->references('id')->on('crm_activities')->onDelete('cascade');
            $table->index('activity_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_activity_attachments');
    }
};

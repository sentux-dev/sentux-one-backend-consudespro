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
        Schema::table('crm_deals', function (Blueprint $table) {
            $table->unsignedBigInteger('pipeline_id')->nullable()->after('amount');
            $table->unsignedBigInteger('stage_id')->nullable()->after('pipeline_id');
            $table->unsignedBigInteger('owner_id')->nullable()->after('stage_id');

            $table->foreign('pipeline_id')->references('id')->on('crm_pipelines')->nullOnDelete();
            $table->foreign('stage_id')->references('id')->on('crm_pipeline_stages')->nullOnDelete();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('crm_deals', function (Blueprint $table) {
            $table->dropForeign(['pipeline_id']);
            $table->dropForeign(['stage_id']);
            $table->dropForeign(['owner_id']);
            $table->dropColumn(['pipeline_id', 'stage_id', 'owner_id']);
        });
    }
};

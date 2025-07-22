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
        Schema::create('crm_contact_real_state_project', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('crm_contact_id');
            $table->unsignedBigInteger('real_state_project_id');
            $table->timestamps();

            $table->foreign('crm_contact_id')->references('id')->on('crm_contacts')->onDelete('cascade');
            $table->foreign('real_state_project_id')->references('id')->on('real_state_projects')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_contact_real_state_project');
    }
};

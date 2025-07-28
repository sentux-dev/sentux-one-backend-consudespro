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
        Schema::create('crm_deal_associations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('deal_id');
            $table->unsignedBigInteger('associable_id');
            $table->string('associable_type'); // 'App\Models\Crm\Contact' o 'App\Models\Crm\Company'
            $table->string('relation_type')->nullable();
            $table->timestamps();

            $table->foreign('deal_id')->references('id')->on('crm_deals')->onDelete('cascade');
            $table->index(['associable_id', 'associable_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('crm_deal_associations');
    }
};

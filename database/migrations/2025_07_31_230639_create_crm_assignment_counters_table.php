<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_assignment_counters', function (Blueprint $table) {
            $table->id();
            // Relación polimórfica: countable puede ser un UserGroup o un Workflow
            $table->morphs('countable'); 
            $table->integer('last_assigned_user_index')->default(-1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_assignment_counters');
    }
};
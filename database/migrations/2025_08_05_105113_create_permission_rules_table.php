<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('permission_rules', function (Blueprint $table) {
            $table->id();
            // La regla se aplica a un ROL específico
            $table->foreignId('role_id')->constrained('roles')->onDelete('cascade');
            
            // El permiso al que se le aplica esta regla (ej: 'contacts.view')
            $table->foreignId('permission_id')->constrained('permissions')->onDelete('cascade');

            // Define si el campo a evaluar es nativo o personalizado
            $table->enum('field_type', ['native', 'custom']);
            
            // El nombre del campo (ej: 'owner_id' o el ID/nombre del campo personalizado)
            $table->string('field_identifier'); 
            
            $table->string('operator'); // ej: 'equals'
            
            // El valor a comparar. Puede ser un valor fijo o una variable dinámica.
            $table->string('value'); // ej: '{user.id}' o 'Costa Rica'
            
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('permission_rules'); }
};
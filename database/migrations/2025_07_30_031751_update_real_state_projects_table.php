<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('real_state_projects', function (Blueprint $table) {
            // Añadir nuevas columnas
            $table->string('slug')->unique()->after('name');
            $table->decimal('square_footage', 10, 2)->nullable()->after('slug');
            $table->decimal('infrastructure_cost', 15, 2)->nullable()->after('square_footage');
            $table->decimal('development_cost', 15, 2)->nullable()->after('infrastructure_cost');
            $table->integer('lot_quantity')->after('development_cost');
            $table->string('status')->default('En Desarrollo')->after('lot_quantity');

            // Eliminar columnas antiguas
            if (Schema::hasColumn('real_state_projects', 'order')) {
                $table->dropColumn('order');
            }
            if (Schema::hasColumn('real_state_projects', 'active')) {
                $table->dropColumn('active');
            }
        });
    }

    public function down(): void {
        Schema::table('real_state_projects', function (Blueprint $table) {
            // Lógica para revertir los cambios
            $table->dropColumn(['slug', 'square_footage', 'infrastructure_cost', 'development_cost', 'lot_quantity', 'status']);
            $table->integer('order')->default(0);
            $table->boolean('active')->default(true);
        });
    }
};
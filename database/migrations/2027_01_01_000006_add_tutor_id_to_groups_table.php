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
        // Verificar que la tabla groups existe antes de intentar modificarla
        if (!Schema::hasTable('groups')) {
            // Si la tabla no existe, esta migración se ejecutará después de que se cree
            return;
        }

        // Verificar si la columna ya existe para evitar errores en re-ejecuciones
        if (Schema::hasColumn('groups', 'tutor_id')) {
            return;
        }

        Schema::table('groups', function (Blueprint $table) {
            $table->foreignId('tutor_id')
                ->nullable()
                ->after('grupo')
                ->constrained('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Verificar que la tabla y la columna existen antes de intentar eliminarlas
        if (!Schema::hasTable('groups') || !Schema::hasColumn('groups', 'tutor_id')) {
            return;
        }

        Schema::table('groups', function (Blueprint $table) {
            $table->dropForeign(['tutor_id']);
            $table->dropColumn('tutor_id');
        });
    }
};





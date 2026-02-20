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
        // Verificar que la tabla student_details existe antes de intentar modificarla
        if (!Schema::hasTable('student_details')) {
            return;
        }

        // Verificar si la columna ya existe para evitar errores en re-ejecuciones
        if (Schema::hasColumn('student_details', 'group_id')) {
            return;
        }

        // Verificar si la tabla groups existe antes de crear la foreign key
        $hasGroups = Schema::hasTable('groups');

        Schema::table('student_details', function (Blueprint $table) use ($hasGroups) {
            if ($hasGroups) {
                $table->foreignId('group_id')->nullable()->after('grupo')->constrained('groups')->onDelete('set null');
            } else {
                // Si groups no existe, crear solo la columna sin foreign key
                $table->unsignedBigInteger('group_id')->nullable()->after('grupo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_details', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropColumn('group_id');
        });
    }
};

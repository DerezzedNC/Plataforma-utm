<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Solo ejecutar si la tabla justificaciones existe
        if (!Schema::hasTable('justificaciones')) {
            return;
        }

        // Verificar si las foreign keys ya existen
        $hasAttendanceForeignKey = $this->hasForeignKey('justificaciones', 'justificaciones_attendance_id_foreign');
        $hasStudentForeignKey = $this->hasForeignKey('justificaciones', 'justificaciones_student_id_foreign');
        $hasRevisadoPorForeignKey = $this->hasForeignKey('justificaciones', 'justificaciones_revisado_por_foreign');

        // Agregar foreign key a attendances si la tabla existe y la foreign key no existe
        if (Schema::hasTable('attendances') && !$hasAttendanceForeignKey && Schema::hasColumn('justificaciones', 'attendance_id')) {
            Schema::table('justificaciones', function (Blueprint $table) {
                $table->foreign('attendance_id')
                    ->references('id')
                    ->on('attendances')
                    ->onDelete('cascade');
            });
        }

        // Agregar foreign key a users para student_id si la tabla existe y la foreign key no existe
        if (Schema::hasTable('users') && !$hasStudentForeignKey && Schema::hasColumn('justificaciones', 'student_id')) {
            Schema::table('justificaciones', function (Blueprint $table) {
                $table->foreign('student_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });
        }

        // Agregar foreign key a users para revisado_por si la tabla existe y la foreign key no existe
        if (Schema::hasTable('users') && !$hasRevisadoPorForeignKey && Schema::hasColumn('justificaciones', 'revisado_por')) {
            Schema::table('justificaciones', function (Blueprint $table) {
                $table->foreign('revisado_por')
                    ->references('id')
                    ->on('users')
                    ->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('justificaciones')) {
            return;
        }

        Schema::table('justificaciones', function (Blueprint $table) {
            // Intentar eliminar las foreign keys si existen
            try {
                $table->dropForeign(['attendance_id']);
            } catch (\Exception $e) {
                // Ignorar si no existe
            }
            
            try {
                $table->dropForeign(['student_id']);
            } catch (\Exception $e) {
                // Ignorar si no existe
            }
            
            try {
                $table->dropForeign(['revisado_por']);
            } catch (\Exception $e) {
                // Ignorar si no existe
            }
        });
    }

    /**
     * Verificar si una foreign key existe (PostgreSQL)
     */
    private function hasForeignKey(string $table, string $constraintName): bool
    {
        try {
            $result = DB::selectOne(
                "SELECT constraint_name 
                 FROM information_schema.table_constraints 
                 WHERE table_name = ? AND constraint_name = ? AND constraint_type = 'FOREIGN KEY'",
                [$table, $constraintName]
            );
            return $result !== null;
        } catch (\Exception $e) {
            return false;
        }
    }
};

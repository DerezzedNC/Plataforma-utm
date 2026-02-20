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
        // Solo ejecutar si la tabla inscripciones existe
        if (!Schema::hasTable('inscripciones')) {
            return;
        }

        // Verificar si las foreign keys ya existen
        $hasStudentForeignKey = $this->hasForeignKey('inscripciones', 'inscripciones_student_id_foreign');
        $hasAcademicLoadForeignKey = $this->hasForeignKey('inscripciones', 'inscripciones_academic_load_id_foreign');

        // Agregar foreign key a users si la tabla existe y la foreign key no existe
        if (Schema::hasTable('users') && !$hasStudentForeignKey && Schema::hasColumn('inscripciones', 'student_id')) {
            Schema::table('inscripciones', function (Blueprint $table) {
                $table->foreign('student_id')
                    ->references('id')
                    ->on('users')
                    ->onDelete('cascade');
            });
        }

        // Agregar foreign key a academic_loads si la tabla existe y la foreign key no existe
        if (Schema::hasTable('academic_loads') && !$hasAcademicLoadForeignKey && Schema::hasColumn('inscripciones', 'academic_load_id')) {
            Schema::table('inscripciones', function (Blueprint $table) {
                $table->foreign('academic_load_id')
                    ->references('id')
                    ->on('academic_loads')
                    ->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('inscripciones')) {
            return;
        }

        Schema::table('inscripciones', function (Blueprint $table) {
            // Intentar eliminar las foreign keys si existen
            try {
                $table->dropForeign(['student_id']);
            } catch (\Exception $e) {
                // Ignorar si no existe
            }
            
            try {
                $table->dropForeign(['academic_load_id']);
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

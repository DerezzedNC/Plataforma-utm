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
        // PRIMERO: Eliminar el índice único antiguo que usaba inscripcion_id y unidad
        // Envolver en try-catch por si el índice ya fue eliminado en un intento anterior
        try {
            Schema::table('calificaciones_detalle', function (Blueprint $table) {
                $table->dropUnique(['inscripcion_id', 'unidad']);
            });
        } catch (\Exception $e) {
            // Ignorar si el índice no existe (ya fue eliminado)
            // Continuar con la migración
        }

        // Intentar eliminar el índice único con diferentes nombres posibles
        $uniqueIndexNames = [
            'calificaciones_detalle_inscripcion_id_unidad_unique',
            'calificaciones_detalle_inscripcion_id_unidad_index',
        ];
        
        foreach ($uniqueIndexNames as $indexName) {
            try {
                DB::statement("DROP INDEX IF EXISTS {$indexName}");
            } catch (\Exception $e) {
                // Ignorar si el índice no existe
            }
        }

        // SEGUNDO: Eliminar el índice simple de unidad (SQLite requiere esto)
        // SQLite no puede eliminar una columna que está siendo usada por un índice
        try {
            Schema::table('calificaciones_detalle', function (Blueprint $table) {
                $table->dropIndex('calificaciones_detalle_unidad_index');
            });
        } catch (\Exception $e) {
            // Ignorar si el índice no existe (ya fue eliminado)
            // Continuar con la migración
        }

        // Intentar eliminar el índice simple con diferentes nombres posibles
        $simpleIndexNames = [
            'calificaciones_detalle_unidad_index',
            'calificaciones_detalle_unidad',
        ];
        
        foreach ($simpleIndexNames as $indexName) {
            try {
                DB::statement("DROP INDEX IF EXISTS {$indexName}");
            } catch (\Exception $e) {
                // Ignorar si el índice no existe
            }
        }

        Schema::table('calificaciones_detalle', function (Blueprint $table) {
            // TERCERO: Eliminar columnas viejas relacionadas con unidades fijas
            // Verificar si las columnas existen antes de eliminarlas
            $columnsToDrop = [];
            
            if (Schema::hasColumn('calificaciones_detalle', 'score_tareas')) {
                $columnsToDrop[] = 'score_tareas';
            }
            if (Schema::hasColumn('calificaciones_detalle', 'score_examen')) {
                $columnsToDrop[] = 'score_examen';
            }
            if (Schema::hasColumn('calificaciones_detalle', 'score_conducta')) {
                $columnsToDrop[] = 'score_conducta';
            }
            if (Schema::hasColumn('calificaciones_detalle', 'promedio_unidad')) {
                $columnsToDrop[] = 'promedio_unidad';
            }
            if (Schema::hasColumn('calificaciones_detalle', 'derecho_examen')) {
                $columnsToDrop[] = 'derecho_examen';
            }
            if (Schema::hasColumn('calificaciones_detalle', 'unidad')) {
                $columnsToDrop[] = 'unidad';
            }
            
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });

        // CUARTO: Agregar nuevas columnas para el sistema dinámico
        // Agregar como nullable para SQLite (compatible con tablas que tienen datos)
        Schema::table('calificaciones_detalle', function (Blueprint $table) {
            $table->unsignedBigInteger('student_id')->nullable()->after('id');
            $table->unsignedBigInteger('course_unit_id')->nullable()->after('student_id');
            $table->integer('saber')->nullable()->comment('Examen/Conocimiento (0-100)');
            $table->integer('saber_hacer_convivir')->nullable()->comment('Tareas/Proyectos/Asistencia (0-100)');
            $table->decimal('calificacion_final_unidad', 5, 2)->nullable()->comment('Promedio o cálculo de las dos métricas');
        });

        // QUINTO: Agregar foreign keys e índices
        Schema::table('calificaciones_detalle', function (Blueprint $table) {
            // Agregar foreign keys (funcionarán aunque sean nullable)
            try {
                $table->foreign('student_id')->references('id')->on('users')->onDelete('cascade');
            } catch (\Exception $e) {
                // Ignorar si ya existe
            }
            try {
                $table->foreign('course_unit_id')->references('id')->on('course_units')->onDelete('cascade');
            } catch (\Exception $e) {
                // Ignorar si ya existe
            }
            
            // Crear índice único para evitar duplicados
            try {
                $table->unique(['student_id', 'course_unit_id']);
            } catch (\Exception $e) {
                // Ignorar si ya existe
            }
            
            // Índices para búsquedas
            try {
                $table->index('student_id');
            } catch (\Exception $e) {
                // Ignorar si ya existe
            }
            try {
                $table->index('course_unit_id');
            } catch (\Exception $e) {
                // Ignorar si ya existe
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('calificaciones_detalle', function (Blueprint $table) {
            // PRIMERO: Eliminar el índice único nuevo
            $table->dropUnique(['student_id', 'course_unit_id']);
        });

        Schema::table('calificaciones_detalle', function (Blueprint $table) {
            // SEGUNDO: Eliminar nuevas columnas
            $table->dropForeign(['student_id']);
            $table->dropForeign(['course_unit_id']);
            $table->dropColumn([
                'student_id',
                'course_unit_id',
                'saber',
                'saber_hacer_convivir',
                'calificacion_final_unidad'
            ]);
        });

        Schema::table('calificaciones_detalle', function (Blueprint $table) {
            // TERCERO: Restaurar columnas viejas
            $table->tinyInteger('unidad')->after('inscripcion_id'); // 1, 2, 3
            $table->decimal('score_tareas', 5, 2)->default(0.00)->after('unidad');
            $table->decimal('score_examen', 5, 2)->nullable()->after('score_tareas');
            $table->decimal('score_conducta', 5, 2)->default(0.00)->after('score_examen');
            $table->decimal('promedio_unidad', 5, 2)->nullable()->after('score_conducta');
            $table->boolean('derecho_examen')->default(true)->after('promedio_unidad');
            
            // CUARTO: Restaurar índice único antiguo
            $table->unique(['inscripcion_id', 'unidad']);
        });
    }
};

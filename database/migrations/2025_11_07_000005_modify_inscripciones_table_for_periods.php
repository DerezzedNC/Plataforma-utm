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
        // PASO 1: Renombrar semestre a cuatrimestre (si existe)
        if (Schema::hasTable('inscripciones')) {
            if (Schema::hasColumn('inscripciones', 'semestre')) {
                // Si existe semestre, necesitamos migrarlo a cuatrimestre
                // Primero crear cuatrimestre si no existe
                if (!Schema::hasColumn('inscripciones', 'cuatrimestre')) {
                    Schema::table('inscripciones', function (Blueprint $table) {
                        $table->string('cuatrimestre')->nullable()->after('academic_load_id');
                    });
                }
                
                // Migrar datos de semestre a cuatrimestre
                // Convertir formato: si semestre es un número, convertirlo a "año-cuatrimestre"
                $inscripciones = DB::table('inscripciones')
                    ->where(function($query) {
                        $query->whereNotNull('semestre')
                              ->where(function($q) {
                                  $q->whereNull('cuatrimestre')
                                    ->orWhere('cuatrimestre', '');
                              });
                    })
                    ->get();
                
                $year = date('Y');
                foreach ($inscripciones as $inscripcion) {
                    // Si semestre es un número, convertirlo a formato "año-cuatrimestre"
                    $semestreValue = $inscripcion->semestre ?? 1; // Por defecto 1 si es null
                    $cuatrimestreValue = $year . '-' . $semestreValue;
                    
                    DB::table('inscripciones')
                        ->where('id', $inscripcion->id)
                        ->update(['cuatrimestre' => $cuatrimestreValue]);
                }
                
                // Asegurar que todos los registros tengan cuatrimestre antes de eliminar semestre
                $year = date('Y');
                DB::table('inscripciones')
                    ->whereNull('cuatrimestre')
                    ->orWhere('cuatrimestre', '')
                    ->update(['cuatrimestre' => $year . '-1']);
                
                // Ahora eliminar la columna semestre
                try {
                    Schema::table('inscripciones', function (Blueprint $table) {
                        $table->dropColumn('semestre');
                    });
                } catch (\Exception $e) {
                    // Si falla, intentar con SQL directo
                    DB::statement('ALTER TABLE inscripciones DROP COLUMN semestre');
                }
            } else {
                // Si no existía semestre, asegurar que cuatrimestre existe
                if (!Schema::hasColumn('inscripciones', 'cuatrimestre')) {
                    Schema::table('inscripciones', function (Blueprint $table) {
                        $table->string('cuatrimestre')->nullable()->after('academic_load_id');
                    });
                }
            }
        }

        // PASO 2: Hacer academic_load_id nullable ANTES de cualquier inserción
        // Esto es CRÍTICO para permitir inscripciones sin academic_load_id
        if (Schema::hasTable('inscripciones') && Schema::hasColumn('inscripciones', 'academic_load_id')) {
            // Primero eliminar la foreign key constraint si existe
            $fkNames = [
                'inscripciones_academic_load_id_foreign',
                'inscripciones_academic_load_id_fk',
                'academic_load_id'
            ];
            
            foreach ($fkNames as $fkName) {
                try {
                    Schema::table('inscripciones', function (Blueprint $table) use ($fkName) {
                        $table->dropForeign([$fkName]);
                    });
                    break; // Si se eliminó exitosamente, salir del loop
                } catch (\Exception $e) {
                    // Continuar con el siguiente nombre
                }
            }
            
            // Ahora hacer la columna nullable
            Schema::table('inscripciones', function (Blueprint $table) {
                $table->unsignedBigInteger('academic_load_id')->nullable()->change();
            });
            
            // Re-agregar la foreign key como nullable (opcional, para mantener integridad referencial)
            try {
                Schema::table('inscripciones', function (Blueprint $table) {
                    $table->foreign('academic_load_id')->references('id')->on('academic_loads')->onDelete('cascade');
                });
            } catch (\Exception $e) {
                // Si no se puede re-agregar, continuar (la columna ya es nullable)
            }
        }

        // PASO 3: Verificar si la tabla existe y crear/modificar según corresponda
        if (!Schema::hasTable('inscripciones')) {
            // Si no existe, crearla con la nueva estructura
            Schema::create('inscripciones', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
                $table->foreignId('academic_period_id')->constrained('academic_periods')->onDelete('cascade');
                $table->string('cuatrimestre'); // Formato: 2025-1, 2025-2, etc.
                $table->string('grupo'); // A, B, C, D, E
                $table->string('status')->default('cursando'); // 'cursando', 'aprobado', 'reprobado', 'baja'
                $table->decimal('promedio_final', 5, 2)->nullable();
                $table->timestamps();
                
                // Índice único para evitar duplicados
                $table->unique(['student_id', 'academic_period_id', 'cuatrimestre', 'grupo']);
                // Índices para búsquedas
                $table->index('student_id');
                $table->index('academic_period_id');
                $table->index('cuatrimestre');
                $table->index('status');
            });
        } else {
            // Si existe, modificarla
            Schema::table('inscripciones', function (Blueprint $table) {
                // Agregar academic_period_id si no existe
                if (!Schema::hasColumn('inscripciones', 'academic_period_id')) {
                    $table->unsignedBigInteger('academic_period_id')->nullable()->after('student_id');
                }
                
                // NO crear semestre - usar cuatrimestre existente
                // Asegurar que cuatrimestre existe (ya debería existir)
                if (!Schema::hasColumn('inscripciones', 'cuatrimestre')) {
                    $table->string('cuatrimestre')->nullable()->after('academic_period_id');
                }
                
                // Agregar grupo si no existe
                if (!Schema::hasColumn('inscripciones', 'grupo')) {
                    $table->string('grupo')->nullable()->after('cuatrimestre');
                }
                
                // Agregar status si no existe
                if (!Schema::hasColumn('inscripciones', 'status')) {
                    $table->string('status')->default('cursando')->after('grupo');
                }
            });

            // Obtener el periodo histórico/activo
            $historicoPeriodId = DB::table('academic_periods')
                ->where('code', 'HISTORICO')
                ->value('id');

            // Si no existe el periodo histórico, crear uno
            if (!$historicoPeriodId) {
                $historicoPeriodId = DB::table('academic_periods')->insertGetId([
                    'name' => 'Histórico/Inicial',
                    'code' => 'HISTORICO',
                    'start_date' => '2000-01-01',
                    'end_date' => '2099-12-31',
                    'is_active' => false,
                    'is_open_for_grades' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            // Migrar datos existentes
            if (Schema::hasColumn('inscripciones', 'academic_load_id')) {
                // Si hay academic_load_id, intentar obtener el periodo de academic_loads
                $inscripciones = DB::table('inscripciones')
                    ->whereNull('academic_period_id')
                    ->get();

                foreach ($inscripciones as $inscripcion) {
                    // Intentar obtener el periodo desde academic_load_id
                    $academicLoad = DB::table('academic_loads')
                        ->where('id', $inscripcion->academic_load_id)
                        ->first();

                    $periodId = $academicLoad && $academicLoad->academic_period_id 
                        ? $academicLoad->academic_period_id 
                        : $historicoPeriodId;

                    // Mantener el cuatrimestre existente o generar uno si no existe
                    $cuatrimestre = $inscripcion->cuatrimestre ?? null;
                    if (!$cuatrimestre) {
                        // Generar cuatrimestre basado en el año actual y un número por defecto
                        $year = date('Y');
                        $cuatrimestre = $year . '-1'; // Por defecto: año actual - cuatrimestre 1
                    }

                    // Obtener grupo desde academic_load si es posible
                    $grupo = 'A'; // Por defecto
                    if ($academicLoad) {
                        $group = DB::table('groups')
                            ->where('id', $academicLoad->group_id)
                            ->first();
                        if ($group) {
                            $grupo = $group->grupo;
                        }
                    }

                    // Actualizar el registro
                    $updateData = [
                        'academic_period_id' => $periodId,
                        'grupo' => $grupo,
                        'status' => isset($inscripcion->aprobado) && $inscripcion->aprobado ? 'aprobado' : 'cursando',
                    ];
                    
                    // Solo actualizar cuatrimestre si no existe
                    if (!$inscripcion->cuatrimestre) {
                        $updateData['cuatrimestre'] = $cuatrimestre;
                    }
                    
                    DB::table('inscripciones')
                        ->where('id', $inscripcion->id)
                        ->update($updateData);
                }
            }

            // Hacer las columnas obligatorias después de migrar datos
            Schema::table('inscripciones', function (Blueprint $table) use ($historicoPeriodId) {
                // Hacer academic_period_id obligatorio
                if (Schema::hasColumn('inscripciones', 'academic_period_id')) {
                    // Asegurar que todos los registros tengan un periodo asignado
                    DB::table('inscripciones')
                        ->whereNull('academic_period_id')
                        ->update(['academic_period_id' => $historicoPeriodId]);
                    
                    $table->unsignedBigInteger('academic_period_id')->nullable(false)->change();
                    
                    // Agregar foreign key si no existe
                    try {
                        $table->foreign('academic_period_id')->references('id')->on('academic_periods')->onDelete('cascade');
                    } catch (\Exception $e) {
                        // La foreign key ya existe, continuar
                    }
                }
                
                // Asegurar que cuatrimestre tenga valores antes de hacerla obligatoria
                if (Schema::hasColumn('inscripciones', 'cuatrimestre')) {
                    // Asignar cuatrimestre por defecto si es null o vacío
                    $year = date('Y');
                    DB::table('inscripciones')
                        ->where(function($query) {
                            $query->whereNull('cuatrimestre')
                                  ->orWhere('cuatrimestre', '');
                        })
                        ->update(['cuatrimestre' => $year . '-1']);
                    
                    // Asegurar que cuatrimestre no sea null
                    try {
                        $table->string('cuatrimestre')->nullable(false)->change();
                    } catch (\Exception $e) {
                        // Si falla, intentar con DB directo
                        DB::statement('ALTER TABLE inscripciones MODIFY cuatrimestre VARCHAR(255) NOT NULL');
                    }
                }
                
                // Hacer grupo obligatorio
                if (Schema::hasColumn('inscripciones', 'grupo')) {
                    // Asignar grupo por defecto si es null
                    DB::table('inscripciones')
                        ->whereNull('grupo')
                        ->update(['grupo' => 'A']);
                    
                    $table->string('grupo')->nullable(false)->change();
                }
            });
        }

        // Script de reparación: Crear inscripciones para alumnos existentes con grupos
        $this->createInscripcionesForExistingStudents();
    }

    /**
     * Crear inscripciones automáticas para alumnos existentes
     */
    private function createInscripcionesForExistingStudents(): void
    {
        // Obtener o crear el periodo activo/inicial
        $activePeriod = DB::table('academic_periods')
            ->where('is_active', true)
            ->first();

        if (!$activePeriod) {
            // Si no hay periodo activo, usar el histórico
            $activePeriod = DB::table('academic_periods')
                ->where('code', 'HISTORICO')
                ->first();
        }

        if (!$activePeriod) {
            // Si no existe ningún periodo, crear uno inicial
            $activePeriodId = DB::table('academic_periods')->insertGetId([
                'name' => 'Periodo Inicial',
                'code' => 'INICIAL',
                'start_date' => now()->startOfYear(),
                'end_date' => now()->endOfYear(),
                'is_active' => true,
                'is_open_for_grades' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } else {
            $activePeriodId = $activePeriod->id;
        }

        // Buscar todos los alumnos que tienen grupos asignados pero no tienen inscripción en el periodo activo
        $students = DB::table('users')
            ->join('student_details', 'users.id', '=', 'student_details.user_id')
            ->whereNotNull('student_details.group_id')
            ->where('users.email', 'like', '%@alumno.utmetropolitana.edu.mx')
            ->select('users.id as student_id', 'student_details.group_id', 'student_details.grado', 'student_details.grupo')
            ->get();

        foreach ($students as $student) {
            // Verificar si ya existe una inscripción para este estudiante en el periodo activo
            $existingInscripcion = DB::table('inscripciones')
                ->where('student_id', $student->student_id)
                ->where('academic_period_id', $activePeriodId)
                ->first();

            if (!$existingInscripcion) {
                // Obtener información del grupo
                $group = DB::table('groups')
                    ->where('id', $student->group_id)
                    ->first();

                if ($group) {
                    // Determinar cuatrimestre desde el grado
                    // El grado es el número de cuatrimestre (1, 2, 3, 4, 5)
                    $grado = $group->grado ?? 1;
                    $year = date('Y');
                    $cuatrimestre = $year . '-' . $grado; // Formato: 2025-1, 2025-2, etc.
                    
                    // Usar el grupo del grupo o del student_detail
                    $grupo = $group->grupo ?? $student->grupo ?? 'A';

                    // Crear la inscripción
                    DB::table('inscripciones')->insert([
                        'student_id' => $student->student_id,
                        'academic_period_id' => $activePeriodId,
                        'cuatrimestre' => $cuatrimestre,
                        'grupo' => $grupo,
                        'status' => 'cursando',
                        'promedio_final' => null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No revertir completamente para evitar pérdida de datos
        // Solo eliminar las foreign keys y columnas nuevas si es necesario
        Schema::table('inscripciones', function (Blueprint $table) {
            if (Schema::hasColumn('inscripciones', 'academic_period_id')) {
                $table->dropForeign(['academic_period_id']);
                $table->dropColumn('academic_period_id');
            }
            // NO eliminar cuatrimestre - es la columna existente que debemos mantener
            if (Schema::hasColumn('inscripciones', 'grupo')) {
                $table->dropColumn('grupo');
            }
            if (Schema::hasColumn('inscripciones', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};


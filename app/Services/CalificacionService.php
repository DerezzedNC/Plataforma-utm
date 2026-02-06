<?php

namespace App\Services;

use App\Models\CalificacionDetalle;
use App\Models\Inscripcion;
use App\Models\Attendance;
use App\Models\AcademicLoad;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CalificacionService
{
    /**
     * Calcular porcentaje de asistencia por unidad
     * 
     * @param int $studentId
     * @param int $academicLoadId
     * @param int $unidad
     * @return float Porcentaje de asistencia (0-100)
     */
    public function calcularPorcentajeAsistencia(int $studentId, int $academicLoadId, int $unidad): float
    {
        // Obtener el schedule_id desde academic_load (cargar ambas relaciones)
        $academicLoad = AcademicLoad::with(['group', 'subject', 'period'])->find($academicLoadId);
        if (!$academicLoad || !$academicLoad->group || !$academicLoad->subject) {
            \Log::warning('No se pudo obtener academic load para calcular asistencia', [
                'academic_load_id' => $academicLoadId,
                'student_id' => $studentId,
                'unidad' => $unidad
            ]);
            return 0; // Devolver 0% si no hay datos
        }

        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        if (!$activePeriod) {
            \Log::warning('No hay periodo académico activo para calcular asistencia');
            return 0;
        }

        // Obtener todos los schedules de esta materia y grupo del periodo activo
        $schedules = \App\Models\Schedule::where('carrera', $academicLoad->group->carrera)
            ->where('grupo', $academicLoad->group->grupo)
            ->where('materia', $academicLoad->subject->nombre)
            ->where('academic_period_id', $activePeriod->id)
            ->pluck('id');

        if ($schedules->isEmpty()) {
            \Log::warning('No se encontraron schedules para calcular asistencia', [
                'carrera' => $academicLoad->group->carrera,
                'grupo' => $academicLoad->group->grupo,
                'materia' => $academicLoad->subject->nombre,
                'periodo' => $activePeriod->id
            ]);
            return 0; // Devolver 0% si no hay schedules
        }

        // USAR LA MISMA LÓGICA QUE LA LISTA DE ALUMNOS:
        // Contar TODAS las asistencias registradas (sin filtrar por unidad) para obtener el porcentaje general
        // Esto es consistente con cómo se muestra en la lista de alumnos
        $hasEstadoColumn = Schema::hasColumn('attendances', 'estado');
        
        // Contar TODAS las asistencias registradas para este estudiante en estos schedules
        $totalClases = Attendance::whereIn('schedule_id', $schedules)
            ->where('student_id', $studentId)
            ->count();

        // Si no hay clases registradas, devolver 0% (datos reales)
        if ($totalClases == 0) {
            \Log::info('No hay clases registradas para este estudiante. Devolviendo 0% (datos reales).', [
                'student_id' => $studentId,
                'academic_load_id' => $academicLoadId,
                'unidad' => $unidad,
                'schedules' => $schedules->toArray()
            ]);
            return 0; // Devolver 0% si no hay clases registradas (datos reales)
        }

        // Contar las asistencias donde el estudiante estuvo presente (sin filtrar por unidad)
        // Usar la misma lógica que AttendanceController@getStudents
        $presentes = 0;
        if ($hasEstadoColumn) {
            // Usar la columna 'estado' (nueva estructura)
            $presentes = Attendance::whereIn('schedule_id', $schedules)
                ->where('student_id', $studentId)
                ->whereIn('estado', ['presente', 'justificado'])
                ->count();
        } else {
            // Fallback para esquema antiguo con columna 'presente' (boolean)
            $presentes = Attendance::whereIn('schedule_id', $schedules)
                ->where('student_id', $studentId)
                ->where('presente', true)
                ->count();
        }

        // Calcular porcentaje: (presentes / total) * 100
        // Misma fórmula que AttendanceController@getStudents
        $porcentaje = $totalClases > 0 ? round(($presentes / $totalClases) * 100) : 0;
        
        // Asegurar que el porcentaje no exceda 100
        $porcentaje = min($porcentaje, 100);

        \Log::info('Porcentaje de asistencia calculado (datos reales, misma lógica que lista de alumnos)', [
            'student_id' => $studentId,
            'academic_load_id' => $academicLoadId,
            'unidad' => $unidad,
            'total_clases' => $totalClases,
            'presentes' => $presentes,
            'porcentaje' => $porcentaje
        ]);

        return $porcentaje;
    }

    /**
     * Verificar si el estudiante tiene derecho a examen (80% de asistencia)
     * 
     * @param int $studentId
     * @param int $academicLoadId
     * @param int $unidad
     * @return bool
     */
    public function tieneDerechoExamen(int $studentId, int $academicLoadId, int $unidad): bool
    {
        $porcentaje = $this->calcularPorcentajeAsistencia($studentId, $academicLoadId, $unidad);
        return $porcentaje >= 80.0;
    }

    /**
     * Calcular el promedio ponderado de una unidad
     * 
     * Fórmula CRÍTICA (SUMA SIMPLE, sin re-escalar):
     * - Si tiene derecho a examen: Tareas 40% + Examen 50% + Conducta 10% = Total (0-100)
     * - Si NO tiene derecho a examen: Tareas 40% + Conducta 10% = Total (0-50, NO re-escalar)
     * 
     * IMPORTANTE: NO re-escalar cuando falta el examen. Simplemente sumar los componentes disponibles.
     * El divisor siempre es 1 (o 100%), no se divide entre la suma de porcentajes activos.
     * 
     * @param CalificacionDetalle $calificacion
     * @return float Promedio calculado (0-10)
     */
    public function calcularPromedioUnidad(CalificacionDetalle $calificacion): float
    {
        // Obtener valores (asegurar que sean números válidos)
        $scoreTareas = (float) ($calificacion->score_tareas ?? 0);
        $scoreExamen = ($calificacion->score_examen !== null && $calificacion->score_examen !== '') 
            ? (float) $calificacion->score_examen 
            : null;
        $scoreConducta = (float) ($calificacion->score_conducta ?? 0);
        
        // Verificar si tiene derecho a examen
        $tieneDerechoExamen = $calificacion->derecho_examen ?? false;
        
        // Calcular puntos reales ponderados (SUMA SIMPLE, sin re-escalar)
        $ptsTareas = $scoreTareas * 0.40;  // Siempre 40%
        $ptsConducta = $scoreConducta * 0.10;  // Siempre 10%
        $ptsExamen = ($tieneDerechoExamen && $scoreExamen !== null) ? ($scoreExamen * 0.50) : 0;  // 50% solo si tiene derecho
        
        // SUMA SIMPLE de los componentes disponibles (CRÍTICO: NO re-escalar)
        $promedioRaw = $ptsTareas + $ptsConducta + $ptsExamen;
        
        \Log::info('Cálculo de promedio (suma simple, sin re-escalar)', [
            'calificacion_id' => $calificacion->id,
            'score_tareas' => $scoreTareas,
            'score_examen' => $scoreExamen,
            'score_conducta' => $scoreConducta,
            'tiene_derecho_examen' => $tieneDerechoExamen,
            'pts_tareas' => $ptsTareas,
            'pts_examen' => $ptsExamen,
            'pts_conducta' => $ptsConducta,
            'promedio_raw' => $promedioRaw
        ]);
        
        // Convertir de escala 0-100 a escala 0-10
        $promedio = $promedioRaw / 10;
        
        // Aplicar redondeo a 2 decimales
        $promedioRedondeado = round($promedio, 2);
        
        // Guardar el promedio en la calificación
        $calificacion->promedio_unidad = $promedioRedondeado;
        $calificacion->save();
        
        return $promedioRedondeado;
    }

    /**
     * Guardar o actualizar calificación de una unidad
     * 
     * @param array $data
     * @return CalificacionDetalle
     * @throws \Exception
     */
    public function guardarCalificacion(array $data): CalificacionDetalle
    {
        DB::beginTransaction();
        
        try {
            $inscripcionId = $data['inscripcion_id'];
            $unidad = $data['unidad'];
            $scoreExamen = $data['score_examen'] ?? null;

            // Obtener inscripción
            $inscripcion = Inscripcion::findOrFail($inscripcionId);
            
            // Verificar derecho a examen
            $tieneDerecho = $this->tieneDerechoExamen(
                $inscripcion->student_id,
                $inscripcion->academic_load_id,
                $unidad
            );

            // Si intenta calificar examen sin derecho, rechazar
            if ($scoreExamen !== null && !$tieneDerecho) {
                throw new \Exception('El estudiante no tiene derecho a examen por faltas excesivas (requiere 80% de asistencia)');
            }

            // Buscar o crear calificación detalle
            $calificacion = CalificacionDetalle::updateOrCreate(
                [
                    'inscripcion_id' => $inscripcionId,
                    'unidad' => $unidad,
                ],
                [
                    'score_tareas' => $data['score_tareas'] ?? 0,
                    'score_examen' => $tieneDerecho ? $scoreExamen : null,
                    'score_conducta' => $data['score_conducta'] ?? 0,
                    'derecho_examen' => $tieneDerecho,
                ]
            );

            // Calcular y guardar el promedio de la unidad automáticamente
            $this->calcularPromedioUnidad($calificacion);
            
            // Recargar la calificación para obtener el promedio actualizado
            $calificacion->refresh();

            // Recalcular promedio final de la inscripción
            $inscripcion->promedio_final = $inscripcion->calcularPromedioFinal();
            $inscripcion->aprobado = $inscripcion->promedio_final >= 7.0;
            $inscripcion->save();

            DB::commit();
            
            return $calificacion->fresh();
            
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error guardando calificación: ' . $e->getMessage(), [
                'data' => $data,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener calificaciones de un grupo y materia
     * Si no hay inscripciones, las crea automáticamente para los estudiantes del grupo
     * Ahora incluye información de actividades
     * 
     * @param int $academicLoadId
     * @param int $unidad
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerCalificacionesGrupo(int $academicLoadId, int $unidad)
    {
        try {
            // Obtener el academic load con sus relaciones
            $academicLoad = AcademicLoad::with(['group', 'subject'])->find($academicLoadId);
            if (!$academicLoad || !$academicLoad->group) {
                return collect([]);
            }

            // Obtener cuatrimestre actual
            $now = now();
            $year = $now->year;
            $month = $now->month;
            $cuatrimestre = 1;
            if ($month >= 5 && $month <= 8) {
                $cuatrimestre = 2;
            } else if ($month >= 9) {
                $cuatrimestre = 3;
            }
            $cuatrimestreStr = "{$year}-{$cuatrimestre}";

            // Obtener el periodo académico activo
            $activePeriod = \App\Models\AcademicPeriod::active()->first();
            if (!$activePeriod) {
                \Log::error('No hay periodo académico activo al obtener calificaciones');
                return collect([]);
            }

            // Obtener estudiantes del grupo desde StudentDetail del periodo activo
            $estudiantes = \App\Models\StudentDetail::where('carrera', $academicLoad->group->carrera)
                ->where('grupo', $academicLoad->group->grupo)
                ->with('user')
                ->get();

            \Log::info('Estudiantes encontrados para grupo', [
                'carrera' => $academicLoad->group->carrera,
                'grupo' => $academicLoad->group->grupo,
                'count' => $estudiantes->count(),
            ]);

            // Crear o actualizar inscripciones para cada estudiante
            foreach ($estudiantes as $estudiante) {
                if ($estudiante->user) {
                    $inscripcion = Inscripcion::firstOrCreate(
                        [
                            'student_id' => $estudiante->user_id,
                            'academic_period_id' => $activePeriod->id,
                            'grupo' => $academicLoad->group->grupo,
                        ],
                        [
                            'academic_load_id' => $academicLoadId,
                            'cuatrimestre' => $cuatrimestreStr,
                            'status' => 'cursando',
                            'promedio_final' => null,
                            'aprobado' => false,
                        ]
                    );
                    
                    // Si la inscripción ya existía, asegurar que tenga academic_load_id
                    if (!$inscripcion->wasRecentlyCreated && (!$inscripcion->academic_load_id || $inscripcion->academic_load_id != $academicLoadId)) {
                        $inscripcion->academic_load_id = $academicLoadId;
                        $inscripcion->save();
                    }
                }
            }

            // Obtener todas las inscripciones del periodo activo y grupo
            $inscripciones = Inscripcion::where('academic_period_id', $activePeriod->id)
                ->where('grupo', $academicLoad->group->grupo)
                ->where('status', 'cursando')
                ->with([
                    'student.studentDetail',
                    'calificacionesDetalle' => function ($query) use ($unidad) {
                        $query->where('unidad', $unidad);
                    }
                ])
                ->get();

            // Filtrar solo estudiantes que pertenecen al grupo correcto (verificar carrera en StudentDetail)
            $inscripciones = $inscripciones->filter(function ($inscripcion) use ($academicLoad) {
                if (!$inscripcion->student || !$inscripcion->student->studentDetail) {
                    return false;
                }
                return $inscripcion->student->studentDetail->carrera === $academicLoad->group->carrera;
            });

            // Asegurar que todas las inscripciones tengan academic_load_id
            foreach ($inscripciones as $inscripcion) {
                if (!$inscripcion->academic_load_id || $inscripcion->academic_load_id != $academicLoadId) {
                    $inscripcion->academic_load_id = $academicLoadId;
                    $inscripcion->save();
                }
            }

            // Obtener actividades de esta unidad (si la tabla existe)
            $actividades = collect([]);
            try {
                if (Schema::hasTable('actividades')) {
                    $actividades = \App\Models\Actividad::where('academic_load_id', $academicLoadId)
                        ->where('unidad', $unidad)
                        ->where('activa', true)
                        ->orderBy('categoria')
                        ->orderBy('created_at')
                        ->get()
                        ->groupBy('categoria');
                }
            } catch (\Exception $e) {
                \Log::warning('Error obteniendo actividades (tabla puede no existir): ' . $e->getMessage());
            }

            // Obtener calculadora de promedios (solo si hay actividades)
            $calculadora = null;
            if ($actividades->isNotEmpty() || Schema::hasTable('actividades')) {
                try {
                    $calculadora = app(\App\Services\CalculadoraDePromedios::class);
                } catch (\Exception $e) {
                    \Log::warning('Error obteniendo calculadora: ' . $e->getMessage());
                }
            }

            return $inscripciones->map(function ($inscripcion) use ($unidad, $academicLoadId, $actividades, $calculadora) {
                if (!$inscripcion->student || !$inscripcion->student->studentDetail) {
                    return null;
                }

                $calificacion = $inscripcion->getCalificacionUnidad($unidad);
                
                // PRIMERO calcular el porcentaje de asistencia (necesario para el recálculo)
                try {
                    $porcentajeAsistencia = $this->calcularPorcentajeAsistencia(
                        $inscripcion->student_id,
                        $academicLoadId,
                        $unidad
                    );
                } catch (\Exception $e) {
                    \Log::error('Error calculando porcentaje de asistencia: ' . $e->getMessage(), [
                        'student_id' => $inscripcion->student_id,
                        'academic_load_id' => $academicLoadId,
                        'unidad' => $unidad,
                        'trace' => $e->getTraceAsString()
                    ]);
                    $porcentajeAsistencia = 0; // Por defecto 0% si hay error (datos reales)
                }
                
                // DESPUÉS recalcular el promedio usando el porcentaje de asistencia correcto
                // Esto asegura que el derecho a examen se calcule correctamente
                // SIEMPRE recalcular, incluso si no hay calificación previa (puede crear una nueva)
                try {
                    \Log::info('Recalculando promedio con porcentaje de asistencia actual', [
                        'inscripcion_id' => $inscripcion->id,
                        'unidad' => $unidad,
                        'porcentaje_asistencia' => $porcentajeAsistencia,
                        'tiene_derecho_examen' => $porcentajeAsistencia >= 80.0,
                        'calificacion_existe' => $calificacion ? 'si' : 'no'
                    ]);
                    $calificacionRecalculada = \App\Services\GradeCalculatorService::recalculateUnitAverage(
                        $inscripcion->id,
                        $unidad
                    );
                    // Usar la calificación recalculada (puede ser nueva o actualizada)
                    $calificacion = $calificacionRecalculada;
                    \Log::info('Promedio recalculado exitosamente', [
                        'inscripcion_id' => $inscripcion->id,
                        'unidad' => $unidad,
                        'promedio_unidad' => $calificacion->promedio_unidad,
                        'score_examen' => $calificacion->score_examen,
                        'derecho_examen' => $calificacion->derecho_examen
                    ]);
                } catch (\Exception $e) {
                    \Log::error('Error recalculando promedio al obtener calificaciones: ' . $e->getMessage(), [
                        'inscripcion_id' => $inscripcion->id,
                        'unidad' => $unidad,
                        'trace' => $e->getTraceAsString()
                    ]);
                    // Si falla el recálculo, intentar obtener la calificación existente
                    if (!$calificacion) {
                        $calificacion = $inscripcion->getCalificacionUnidad($unidad);
                    }
                }

                // Obtener resumen de actividades por categoría (solo si hay calculadora)
                $resumenTareas = null;
                $resumenExamen = null;
                $resumenConducta = null;
                
                if ($calculadora) {
                    try {
                        $resumenTareas = $calculadora->obtenerResumenActividades(
                            $inscripcion->student_id,
                            $academicLoadId,
                            $unidad,
                            'TAREA'
                        );

                        $resumenExamen = $calculadora->obtenerResumenActividades(
                            $inscripcion->student_id,
                            $academicLoadId,
                            $unidad,
                            'EXAMEN'
                        );

                        $resumenConducta = $calculadora->obtenerResumenActividades(
                            $inscripcion->student_id,
                            $academicLoadId,
                            $unidad,
                            'CONDUCTA'
                        );
                    } catch (\Exception $e) {
                        \Log::warning('Error obteniendo resumen de actividades: ' . $e->getMessage());
                    }
                }

                // Asegurar que la calificación esté actualizada antes de devolverla
                if ($calificacion && $calificacion->exists) {
                    $calificacion->refresh(); // Recargar desde BD para obtener valores más recientes
                }
                
                return [
                    'inscripcion_id' => $inscripcion->id,
                    'student_id' => $inscripcion->student_id,
                    'student_name' => $inscripcion->student->name ?? 'Sin nombre',
                    'matricula' => $inscripcion->student->studentDetail->matricula ?? '',
                    'porcentaje_asistencia' => $porcentajeAsistencia,
                    'tiene_derecho_examen' => $porcentajeAsistencia >= 80.0,
                    'calificacion' => $calificacion ? [
                        'id' => $calificacion->id,
                        'score_tareas' => $calificacion->score_tareas ?? 0,
                        'score_examen' => $calificacion->score_examen, // Debe ser null si no tiene derecho
                        'score_conducta' => $calificacion->score_conducta ?? 0,
                        'promedio_unidad' => $calificacion->promedio_unidad ?? null, // Promedio recalculado
                        'derecho_examen' => $calificacion->derecho_examen ?? false,
                    ] : [
                        'id' => null,
                        'score_tareas' => 0,
                        'score_examen' => null,
                        'score_conducta' => 0,
                        'promedio_unidad' => null,
                        'derecho_examen' => false,
                    ],
                    'actividades' => [
                        'TAREA' => $actividades->get('TAREA', collect())->values(),
                        'EXAMEN' => $actividades->get('EXAMEN', collect())->values(),
                        'CONDUCTA' => $actividades->get('CONDUCTA', collect())->values(),
                    ],
                    'resumen' => [
                        'TAREA' => $resumenTareas ?? ['actividades' => [], 'total_posible' => 0, 'total_obtenido' => 0, 'porcentaje' => 0, 'score_ponderado' => 0],
                        'EXAMEN' => $resumenExamen ?? ['actividades' => [], 'total_posible' => 0, 'total_obtenido' => 0, 'porcentaje' => 0, 'score_ponderado' => 0],
                        'CONDUCTA' => $resumenConducta ?? ['actividades' => [], 'total_posible' => 0, 'total_obtenido' => 0, 'porcentaje' => 0, 'score_ponderado' => 0],
                    ],
                ];
            })->filter(); // Filtrar valores null
        } catch (\Exception $e) {
            \Log::error('Error obteniendo calificaciones grupo: ' . $e->getMessage(), [
                'academic_load_id' => $academicLoadId,
                'unidad' => $unidad,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}


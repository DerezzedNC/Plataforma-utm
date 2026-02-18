<?php

namespace App\Services;

use App\Models\CalificacionDetalle;
use App\Models\Inscripcion;
use App\Models\Attendance;
use App\Models\AcademicLoad;
use App\Models\CourseUnit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Carbon\Carbon;

class CalificacionService
{
    /**
     * Calcular porcentaje de asistencia por unidad usando course_unit_id
     * 
     * @param int $studentId
     * @param int $academicLoadId
     * @param int $courseUnitId ID de la unidad del curso (obligatorio)
     * @return float Porcentaje de asistencia (0-100)
     */
    public function calcularPorcentajeAsistencia(int $studentId, int $academicLoadId, int $courseUnitId): float
    {
        // Verificar que la unidad del curso existe y pertenece a esta carga académica
        $courseUnit = CourseUnit::where('id', $courseUnitId)
            ->where('academic_load_id', $academicLoadId)
            ->first();
        
        if (!$courseUnit) {
            \Log::warning('Unidad de curso no encontrada o no pertenece a esta carga académica', [
                'course_unit_id' => $courseUnitId,
                'academic_load_id' => $academicLoadId,
                'student_id' => $studentId
            ]);
            return 0;
        }

        // Obtener el schedule_id desde academic_load
        $academicLoad = AcademicLoad::with(['group', 'subject', 'period'])->find($academicLoadId);
        if (!$academicLoad || !$academicLoad->group || !$academicLoad->subject) {
            \Log::warning('No se pudo obtener academic load para calcular asistencia', [
                'academic_load_id' => $academicLoadId,
                'student_id' => $studentId,
                'course_unit_id' => $courseUnitId
            ]);
            return 0;
        }

        // Obtener el periodo académico
        $period = $academicLoad->period;
        if (!$period) {
            $period = \App\Models\AcademicPeriod::active()->first();
        }
        
        if (!$period) {
            \Log::warning('No hay periodo académico válido para calcular asistencia');
            return 0;
        }

        // Obtener todos los schedules de esta materia y grupo del periodo
        $schedules = \App\Models\Schedule::where('carrera', $academicLoad->group->carrera)
            ->where('grupo', $academicLoad->group->grupo)
            ->where('materia', $academicLoad->subject->nombre)
            ->where('academic_period_id', $period->id)
            ->pluck('id');

        if ($schedules->isEmpty()) {
            \Log::warning('No se encontraron schedules para calcular asistencia', [
                'carrera' => $academicLoad->group->carrera,
                'grupo' => $academicLoad->group->grupo,
                'materia' => $academicLoad->subject->nombre,
                'periodo' => $period->id
            ]);
            return 0;
        }

        // Verificar si la columna course_unit_id existe
        $hasCourseUnitIdColumn = Schema::hasColumn('attendances', 'course_unit_id');
        $hasEstadoColumn = Schema::hasColumn('attendances', 'estado');
        
        // Construir query base: filtrar por schedule, estudiante y course_unit_id
        $query = Attendance::whereIn('schedule_id', $schedules)
            ->where('student_id', $studentId);
        
        // Si la columna course_unit_id existe, filtrar estrictamente por unidad
        // IGNORAR completamente las asistencias sin course_unit_id (de prueba)
        if ($hasCourseUnitIdColumn) {
            $query->where('course_unit_id', $courseUnitId);
        } else {
            // Si la columna no existe, devolver 0% (no se puede calcular por unidad)
            \Log::warning('La columna course_unit_id no existe en la tabla attendances. No se puede calcular asistencia por unidad.');
            return 0;
        }
        
        // Contar el total de clases registradas para esta unidad
        $totalClases = $query->count();

        // Si no hay clases registradas para esta unidad, devolver 0%
        if ($totalClases == 0) {
            \Log::info('No hay clases registradas para esta unidad. Devolviendo 0%.', [
                'student_id' => $studentId,
                'academic_load_id' => $academicLoadId,
                'course_unit_id' => $courseUnitId,
                'schedules' => $schedules->toArray()
            ]);
            return 0;
        }

        // Contar las asistencias donde el estudiante estuvo presente en esta unidad
        $presentes = 0;
        if ($hasEstadoColumn) {
            // Usar la columna 'estado' (nueva estructura)
            $presentes = (clone $query)->whereIn('estado', ['presente', 'justificado'])->count();
        } else {
            // Fallback para esquema antiguo con columna 'presente' (boolean)
            $presentes = (clone $query)->where('presente', true)->count();
        }

        // Calcular porcentaje: (presentes / total) * 100
        $porcentaje = $totalClases > 0 ? round(($presentes / $totalClases) * 100) : 0;
        
        // Asegurar que el porcentaje no exceda 100
        $porcentaje = min($porcentaje, 100);

        \Log::info('Porcentaje de asistencia calculado por unidad', [
            'student_id' => $studentId,
            'academic_load_id' => $academicLoadId,
            'course_unit_id' => $courseUnitId,
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
     * @param int $courseUnitId ID de la unidad del curso (obligatorio)
     * @return bool
     */
    public function tieneDerechoExamen(int $studentId, int $academicLoadId, int $courseUnitId): bool
    {
        $porcentaje = $this->calcularPorcentajeAsistencia($studentId, $academicLoadId, $courseUnitId);
        return $porcentaje >= 80.0;
    }

    /**
     * Calcular la calificación final de una unidad
     * Modelo 60-40: Saber * 0.6 + Saber Hacer * 0.4
     * 
     * @param CalificacionDetalle $calificacion
     * @return float Calificación final calculada (0-100)
     */
    public function calcularCalificacionFinalUnidad(CalificacionDetalle $calificacion): float
    {
        $saber = (int) ($calificacion->saber ?? 0);
        $saberHacer = (int) ($calificacion->saber_hacer_convivir ?? 0);
        
        // Calcular con fórmula 60-40: Saber * 0.6 + Saber Hacer * 0.4
        if ($saber > 0 || $saberHacer > 0) {
            $calificacionFinal = ($saber * 0.6) + ($saberHacer * 0.4);
            $calificacionFinalRedondeada = round($calificacionFinal, 2);
            
            // Guardar el promedio en la calificación
            $calificacion->calificacion_final_unidad = $calificacionFinalRedondeada;
            $calificacion->save();
            
            \Log::info('Calificación final de unidad calculada (60-40)', [
                'calificacion_id' => $calificacion->id,
                'saber' => $saber,
                'saber_hacer' => $saberHacer,
                'calificacion_final_unidad' => $calificacionFinalRedondeada
            ]);
            
            return $calificacionFinalRedondeada;
        }
        
        // Si no hay valores, devolver 0
        $calificacion->calificacion_final_unidad = null;
        $calificacion->save();
        
        return 0.0;
    }

    /**
     * Guardar o actualizar calificación de una unidad
     * 
     * @param array $data Debe contener: student_id, course_unit_id, saber, saber_hacer_convivir
     * @return CalificacionDetalle
     * @throws \Exception
     */
    public function guardarCalificacion(array $data): CalificacionDetalle
    {
        DB::beginTransaction();
        
        try {
            $studentId = $data['student_id'];
            $courseUnitId = $data['course_unit_id'];
            $saber = isset($data['saber']) ? (int) $data['saber'] : null;
            $saberHacerConvivir = isset($data['saber_hacer_convivir']) ? (int) $data['saber_hacer_convivir'] : null;

            // Validar que los valores estén en el rango 0-100
            if ($saber !== null && ($saber < 0 || $saber > 100)) {
                throw new \Exception('El valor de "saber" debe estar entre 0 y 100');
            }
            if ($saberHacerConvivir !== null && ($saberHacerConvivir < 0 || $saberHacerConvivir > 100)) {
                throw new \Exception('El valor de "saber_hacer_convivir" debe estar entre 0 y 100');
            }

            // Verificar que la unidad del curso existe
            $courseUnit = CourseUnit::findOrFail($courseUnitId);

            // Obtener la inscripción para el inscripcion_id (requerido por la base de datos)
            $inscripcion = Inscripcion::where('student_id', $studentId)
                ->where('academic_load_id', $courseUnit->academic_load_id)
                ->first();
            
            if (!$inscripcion) {
                throw new \Exception('No se encontró la inscripción para este estudiante y materia');
            }

            // Buscar calificación existente para preservar valores que no se están actualizando
            $calificacionExistente = CalificacionDetalle::where('student_id', $studentId)
                ->where('course_unit_id', $courseUnitId)
                ->first();

            // Si existe, preservar los valores que no se están enviando
            if ($calificacionExistente) {
                // Solo actualizar los valores que se están enviando explícitamente (no null)
                if ($saber !== null) {
                    $calificacionExistente->saber = $saber;
                }
                if ($saberHacerConvivir !== null) {
                    $calificacionExistente->saber_hacer_convivir = $saberHacerConvivir;
                }
                $calificacionExistente->save();
                $calificacion = $calificacionExistente;
            } else {
                // Crear nueva calificación con todos los valores
                $calificacion = CalificacionDetalle::create([
                    'inscripcion_id' => $inscripcion->id, // Requerido por la base de datos
                    'student_id' => $studentId,
                    'course_unit_id' => $courseUnitId,
                    'saber' => $saber,
                    'saber_hacer_convivir' => $saberHacerConvivir,
                ]);
            }

            // Calcular y guardar la calificación final de la unidad automáticamente
            $this->calcularCalificacionFinalUnidad($calificacion);
            
            // Recargar la calificación para obtener los valores actualizados
            $calificacion->refresh();
            
            if ($inscripcion) {
                $inscripcion->promedio_final = $inscripcion->calcularPromedioFinal();
                $inscripcion->aprobado = $inscripcion->promedio_final >= 7.0;
                $inscripcion->save();
            }

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
     * Obtener calificaciones de un grupo y unidad de curso
     * Si no hay inscripciones, las crea automáticamente para los estudiantes del grupo
     * 
     * @param int $academicLoadId
     * @param int $courseUnitId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function obtenerCalificacionesGrupo(int $academicLoadId, int $courseUnitId)
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

            // Verificar que la unidad del curso existe y pertenece a esta carga académica
            $courseUnit = CourseUnit::where('id', $courseUnitId)
                ->where('academic_load_id', $academicLoadId)
                ->first();
            
            if (!$courseUnit) {
                \Log::error('Unidad de curso no encontrada o no pertenece a esta carga académica', [
                    'course_unit_id' => $courseUnitId,
                    'academic_load_id' => $academicLoadId
                ]);
                return collect([]);
            }

            // Obtener todas las inscripciones del periodo activo y grupo
            $inscripciones = Inscripcion::where('academic_period_id', $activePeriod->id)
                ->where('grupo', $academicLoad->group->grupo)
                ->where('status', 'cursando')
                ->with([
                    'student.studentDetail',
                    'calificacionesDetalle' => function ($query) use ($courseUnitId) {
                        $query->where('course_unit_id', $courseUnitId);
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

            return $inscripciones->map(function ($inscripcion) use ($courseUnitId, $academicLoadId) {
                if (!$inscripcion->student || !$inscripcion->student->studentDetail) {
                    return null;
                }

                // Obtener calificación de esta unidad de curso
                $calificacion = CalificacionDetalle::where('student_id', $inscripcion->student_id)
                    ->where('course_unit_id', $courseUnitId)
                    ->first();
                
                return [
                    'inscripcion_id' => $inscripcion->id,
                    'student_id' => $inscripcion->student_id,
                    'student_name' => $inscripcion->student->name ?? 'Sin nombre',
                    'matricula' => $inscripcion->student->studentDetail->matricula ?? '',
                    'calificacion' => $calificacion ? [
                        'id' => $calificacion->id,
                        'saber' => $calificacion->saber ?? null,
                        'saber_hacer_convivir' => $calificacion->saber_hacer_convivir ?? null,
                        'calificacion_final_unidad' => $calificacion->calificacion_final_unidad ?? null,
                    ] : [
                        'id' => null,
                        'saber' => null,
                        'saber_hacer_convivir' => null,
                        'calificacion_final_unidad' => null,
                    ],
                ];
            })->filter(); // Filtrar valores null
        } catch (\Exception $e) {
            \Log::error('Error obteniendo calificaciones grupo: ' . $e->getMessage(), [
                'academic_load_id' => $academicLoadId,
                'course_unit_id' => $courseUnitId,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}


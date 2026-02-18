<?php

namespace App\Services;

use App\Models\Actividad;
use App\Models\ActividadEntrega;
use App\Models\CalificacionDetalle;
use App\Models\Inscripcion;
use App\Traits\CustomRounding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CalculadoraDePromedios
{
    use CustomRounding;

    /**
     * Calcular el porcentaje de una categoría basado en actividades
     * 
     * @param int $studentId
     * @param int $academicLoadId
     * @param int $unidad
     * @param string $categoria (TAREA, EXAMEN, CONDUCTA)
     * @return float Porcentaje calculado (0-100)
     */
    public function calcularPorcentajeCategoria(
        int $studentId,
        int $academicLoadId,
        int $unidad,
        string $categoria
    ): float {
        // Verificar si la tabla existe
        if (!\Illuminate\Support\Facades\Schema::hasTable('actividades')) {
            return 0.00;
        }

        // Obtener todas las actividades de esta categoría y unidad
        $actividades = Actividad::where('academic_load_id', $academicLoadId)
            ->where('unidad', $unidad)
            ->where('categoria', $categoria)
            ->where('activa', true)
            ->get();

        if ($actividades->isEmpty()) {
            return 0.00;
        }

        // Calcular total posible (suma de valor_maximo de todas las actividades)
        $totalPosible = $actividades->sum('valor_maximo');

        if ($totalPosible == 0) {
            return 0.00;
        }

        // Calcular total obtenido por el estudiante
        $totalObtenido = 0;
        foreach ($actividades as $actividad) {
            $entrega = $actividad->getEntregaEstudiante($studentId);
            if ($entrega) {
                $totalObtenido += $entrega->calificacion_obtenida;
            }
        }

        // Aplicar regla de 3: (TotalObtenido / TotalPosible) * 10
        // Multiplicamos por 10 porque el sistema usa escala 0-10
        $porcentaje = ($totalObtenido / $totalPosible) * 10;

        // Limitar a máximo 10
        $porcentaje = min(10.0, max(0.0, $porcentaje));

        return $this->customRound($porcentaje, 2);
    }

    /**
     * Calcular el score final de una categoría aplicando el porcentaje de ponderación
     * 
     * @param int $studentId
     * @param int $academicLoadId
     * @param int $unidad
     * @param string $categoria
     * @param float $porcentajePonderacion (40, 50, 10)
     * @return float Score final ponderado
     */
    public function calcularScorePonderado(
        int $studentId,
        int $academicLoadId,
        int $unidad,
        string $categoria,
        float $porcentajePonderacion
    ): float {
        $porcentajeCategoria = $this->calcularPorcentajeCategoria(
            $studentId,
            $academicLoadId,
            $unidad,
            $categoria
        );

        // Aplicar ponderación: (porcentajeCategoria / 10) * porcentajePonderacion
        // Ejemplo: Si tiene 8.5/10 en tareas y la ponderación es 40%
        // Score = (8.5 / 10) * 40 = 3.4 puntos de 40
        $score = ($porcentajeCategoria / 10) * $porcentajePonderacion;

        return $this->customRound($score, 2);
    }

    /**
     * Actualizar calificación en calificaciones_detalle basado en actividades
     * 
     * @param int $studentId
     * @param int $academicLoadId
     * @param int $unidad
     * @return CalificacionDetalle
     */
    public function actualizarCalificacionDesdeActividades(
        int $studentId,
        int $academicLoadId,
        int $unidad
    ): CalificacionDetalle {
        try {
            // Verificar si las tablas existen
            if (!\Illuminate\Support\Facades\Schema::hasTable('actividades') || 
                !\Illuminate\Support\Facades\Schema::hasTable('actividades_entregas')) {
                // Si no existen las tablas, no hacer nada y retornar null o la calificación existente
                $inscripcion = Inscripcion::where('student_id', $studentId)
                    ->where('academic_load_id', $academicLoadId)
                    ->first();
                
                if ($inscripcion) {
                    return $inscripcion->getCalificacionUnidad($unidad) ?? new CalificacionDetalle();
                }
                
                return new CalificacionDetalle();
            }

            DB::beginTransaction();

            // Obtener o crear inscripción
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

            $inscripcion = Inscripcion::firstOrCreate(
                [
                    'student_id' => $studentId,
                    'academic_load_id' => $academicLoadId,
                    'cuatrimestre' => $cuatrimestreStr,
                ],
                [
                    'promedio_final' => null,
                    'aprobado' => false,
                ]
            );

            // Calcular scores de cada categoría
            $scoreTareas = $this->calcularScorePonderado(
                $studentId,
                $academicLoadId,
                $unidad,
                'TAREA',
                40.0
            );

            $scoreExamen = $this->calcularScorePonderado(
                $studentId,
                $academicLoadId,
                $unidad,
                'EXAMEN',
                50.0
            );

            $scoreConducta = $this->calcularScorePonderado(
                $studentId,
                $academicLoadId,
                $unidad,
                'CONDUCTA',
                10.0
            );

            // Verificar derecho a examen (usar el servicio de calificaciones)
            // Obtener course_unit_id desde el número de unidad (sistema antiguo)
            $courseUnit = \App\Models\CourseUnit::where('academic_load_id', $academicLoadId)
                ->orderBy('id')
                ->skip($unidad - 1)
                ->first();
            
            $calificacionService = app(\App\Services\CalificacionService::class);
            if ($courseUnit) {
                $tieneDerechoExamen = $calificacionService->tieneDerechoExamen(
                    $studentId,
                    $academicLoadId,
                    $courseUnit->id
                );
            } else {
                \Log::warning('No se encontró course_unit para unidad numérica en CalculadoraDePromedios', [
                    'academic_load_id' => $academicLoadId,
                    'unidad' => $unidad
                ]);
                // Si no hay course_unit, asumir que tiene derecho (comportamiento antiguo)
                $tieneDerechoExamen = true;
            }

            // Si no tiene derecho a examen, el score_examen no cuenta
            if (!$tieneDerechoExamen) {
                $scoreExamen = 0;
            }

            // Buscar o crear calificación detalle
            $calificacion = CalificacionDetalle::updateOrCreate(
                [
                    'inscripcion_id' => $inscripcion->id,
                    'unidad' => $unidad,
                ],
                [
                    'score_tareas' => $scoreTareas,
                    'score_examen' => $tieneDerechoExamen ? $scoreExamen : null,
                    'score_conducta' => $scoreConducta,
                    'derecho_examen' => $tieneDerechoExamen,
                ]
            );

            // El promedio_unidad se calcula automáticamente en el modelo
            // Recalcular promedio final de la inscripción
            $inscripcion->promedio_final = $inscripcion->calcularPromedioFinal();
            $inscripcion->aprobado = $inscripcion->promedio_final >= 7.0;
            $inscripcion->save();

            DB::commit();

            return $calificacion->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error actualizando calificación desde actividades: ' . $e->getMessage(), [
                'student_id' => $studentId,
                'academic_load_id' => $academicLoadId,
                'unidad' => $unidad,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Obtener resumen de actividades de un estudiante por categoría
     * 
     * @param int $studentId
     * @param int $academicLoadId
     * @param int $unidad
     * @param string $categoria
     * @return array
     */
    public function obtenerResumenActividades(
        int $studentId,
        int $academicLoadId,
        int $unidad,
        string $categoria
    ): array {
        // Verificar si la tabla existe
        if (!\Illuminate\Support\Facades\Schema::hasTable('actividades')) {
            return [
                'actividades' => [],
                'total_posible' => 0,
                'total_obtenido' => 0,
                'porcentaje' => 0,
                'score_ponderado' => 0,
            ];
        }

        $actividades = Actividad::where('academic_load_id', $academicLoadId)
            ->where('unidad', $unidad)
            ->where('categoria', $categoria)
            ->where('activa', true)
            ->orderBy('created_at')
            ->get();

        $totalPosible = $actividades->sum('valor_maximo');
        $totalObtenido = 0;
        $actividadesData = [];

        foreach ($actividades as $actividad) {
            $calificacion = 0;
            if (\Illuminate\Support\Facades\Schema::hasTable('actividades_entregas')) {
                $entrega = ActividadEntrega::where('actividad_id', $actividad->id)
                    ->where('student_id', $studentId)
                    ->first();
                $calificacion = $entrega ? (float) $entrega->calificacion_obtenida : 0;
            }
            $totalObtenido += $calificacion;

            $actividadesData[] = [
                'id' => $actividad->id,
                'titulo' => $actividad->titulo,
                'descripcion' => $actividad->descripcion,
                'valor_maximo' => (float) $actividad->valor_maximo,
                'calificacion_obtenida' => $calificacion,
                'entrega_id' => $entrega ? $entrega->id : null,
            ];
        }

        // Calcular porcentaje en escala 0-100 (no 0-10) para ser consistente con examen y conducta
        $porcentaje = $totalPosible > 0 
            ? ($totalObtenido / $totalPosible) * 100 
            : 0;
        $porcentaje = min(100.0, max(0.0, $porcentaje));

        return [
            'actividades' => $actividadesData,
            'total_posible' => $totalPosible,
            'total_obtenido' => $totalObtenido,
            'porcentaje' => $this->customRound($porcentaje, 2),
            'score_ponderado' => $this->calcularScorePonderado(
                $studentId,
                $academicLoadId,
                $unidad,
                $categoria,
                $categoria === 'TAREA' ? 40.0 : ($categoria === 'EXAMEN' ? 50.0 : 10.0)
            ),
        ];
    }
}


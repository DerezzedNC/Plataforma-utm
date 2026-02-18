<?php

namespace App\Services;

use App\Models\CalificacionDetalle;
use App\Models\Inscripcion;
use App\Models\Actividad;
use App\Models\ActividadEntrega;
use App\Traits\CustomRounding;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class GradeCalculatorService
{
    use CustomRounding;

    /**
     * Recalcular el promedio de una unidad
     * 
     * @param int $inscripcionId
     * @param int $unidad
     * @return CalificacionDetalle
     */
    public static function recalculateUnitAverage(int $inscripcionId, int $unidad): CalificacionDetalle
    {
        try {
            DB::beginTransaction();

            // Obtener la inscripción
            $inscripcion = Inscripcion::findOrFail($inscripcionId);
            $academicLoadId = $inscripcion->academic_load_id;

            // Buscar o crear calificación detalle
            // Usar lockForUpdate para evitar condiciones de carrera
            $calificacion = CalificacionDetalle::where('inscripcion_id', $inscripcionId)
                ->where('unidad', $unidad)
                ->lockForUpdate()
                ->first();

            // Si no existe, crear nueva
            $esNuevoRegistro = false;
            if (!$calificacion) {
                $esNuevoRegistro = true;
                $calificacion = new CalificacionDetalle();
                $calificacion->inscripcion_id = $inscripcionId;
                $calificacion->unidad = $unidad;
                $calificacion->score_tareas = 0;
                $calificacion->score_examen = null;
                $calificacion->score_conducta = 0.00; // Default para NOT NULL
                $calificacion->derecho_examen = true;
            }

            // Guardar valores actuales de examen y conducta ANTES de modificar
            // IMPORTANTE: Si el registro ya existe, recargar desde BD para asegurar valores más recientes
            // Esto previene que se pierdan cuando se recalcula desde actividades
            if (!$esNuevoRegistro && $calificacion->exists) {
                // Recargar desde BD para obtener los valores más actuales
                $calificacion->refresh();
            }
            $scoreExamenAnterior = $calificacion->score_examen;
            $scoreConductaAnterior = $calificacion->score_conducta ?? 0.00; // Asegurar que nunca sea null
            
            // Verificar derecho a examen actual
            // Obtener course_unit_id desde el número de unidad (sistema antiguo)
            $courseUnit = \App\Models\CourseUnit::where('academic_load_id', $academicLoadId)
                ->orderBy('id')
                ->skip($unidad - 1)
                ->first();
            
            if (!$courseUnit) {
                \Log::warning('No se encontró course_unit para unidad numérica', [
                    'academic_load_id' => $academicLoadId,
                    'unidad' => $unidad
                ]);
                // Si no hay course_unit, asumir que tiene derecho (comportamiento antiguo)
                $tieneDerechoExamenActual = true;
            } else {
                $calificacionService = app(\App\Services\CalificacionService::class);
                $tieneDerechoExamenActual = $calificacionService->tieneDerechoExamen(
                    $inscripcion->student_id,
                    $academicLoadId,
                    $courseUnit->id
                );
            }
            // Usar el derecho a examen actual (puede cambiar según asistencia)
            $derechoExamenAnterior = $tieneDerechoExamenActual;

            // 1. Calcular Score Tareas (40%) - desde actividades
            // Esto retorna un valor de 0-100 (porcentaje)
            $rawScoreTareas = self::calculateTareasScore($inscripcionId, $academicLoadId, $unidad);

            // 2. Obtener Score Examen y Conducta de la calificación actual
            // CRÍTICO: Si no tiene derecho a examen, el score_examen debe ser null INMEDIATAMENTE
            // No importa si tenía un valor anterior - si no tiene derecho, no cuenta
            if (!$derechoExamenAnterior) {
                // Si no tiene derecho a examen, el score_examen debe ser null
                $scoreExamen = null;
                // Si tenía un valor anterior, registrarlo para logging pero no usarlo
                if ($scoreExamenAnterior !== null) {
                    \Log::warning('Estudiante sin derecho a examen pero tenía score_examen previo - eliminando', [
                        'inscripcion_id' => $inscripcionId,
                        'unidad' => $unidad,
                        'score_examen_anterior' => $scoreExamenAnterior,
                        'porcentaje_asistencia' => $courseUnit 
                            ? $calificacionService->calcularPorcentajeAsistencia(
                                $inscripcion->student_id,
                                $academicLoadId,
                                $courseUnit->id
                            )
                            : 0
                    ]);
                }
            } else {
                // Solo usar el score_examen si tiene derecho
                $scoreExamen = ($scoreExamenAnterior !== null && $scoreExamenAnterior !== '') ? (float) $scoreExamenAnterior : null;
            }
            
            $scoreConducta = ($scoreConductaAnterior !== null && $scoreConductaAnterior !== '') ? (float) $scoreConductaAnterior : 0.00;

            // Si no tiene derecho a examen (menos de 80% de asistencia), el examen NO cuenta para el cálculo
            // CRÍTICO: Si no tiene derecho a examen, el score_examen debe ser null y NO debe usarse en el cálculo
            if (!$derechoExamenAnterior) {
                // El examen NO cuenta si no tiene derecho - establecer como null y no usar en cálculo
                $scoreExamenFinal = null; // No usar en cálculo
                $scoreExamenParaGuardar = null; // Guardar como null en BD
                
                // Si ya tenía un valor de examen guardado, eliminarlo porque no tiene derecho
                // Esto asegura que el examen no se use en ningún cálculo futuro
                if ($scoreExamen !== null) {
                    \Log::info('Estudiante perdió derecho a examen - eliminando score_examen previo', [
                        'inscripcion_id' => $inscripcionId,
                        'unidad' => $unidad,
                        'score_examen_anterior' => $scoreExamen,
                        'porcentaje_asistencia' => $courseUnit 
                            ? $calificacionService->calcularPorcentajeAsistencia(
                                $inscripcion->student_id,
                                $academicLoadId,
                                $courseUnit->id
                            )
                            : 0
                    ]);
                }
            } else {
                // Solo usar el examen si tiene derecho Y tiene un valor
                $scoreExamenFinal = ($scoreExamen !== null) ? (float) $scoreExamen : 0;
                $scoreExamenParaGuardar = $scoreExamen; // Preservar valor si tiene derecho
            }

            // 3. Cálculo Final (SUMA SIMPLE, sin re-escalar)
            // CRÍTICO: NO re-escalar cuando falta el examen. Simplemente sumar los componentes disponibles.
            // Todos los valores están en escala 0-100, se aplica la ponderación original
            // Luego se divide entre 10 para obtener escala 0-10
            
            // Calcular puntos reales ponderados (mantener porcentajes originales)
            $ptsTareas = $rawScoreTareas * 0.40;  // Siempre 40%
            $ptsConducta = $scoreConducta * 0.10;  // Siempre 10%
            $ptsExamen = ($derechoExamenAnterior && $scoreExamenFinal !== null) ? ($scoreExamenFinal * 0.50) : 0;  // 50% solo si tiene derecho
            
            // SUMA SIMPLE de los componentes disponibles (NO re-escalar)
            $promedioRaw = $ptsTareas + $ptsConducta + $ptsExamen;
            
            \Log::info('Cálculo de promedio (suma simple, sin re-escalar)', [
                'inscripcion_id' => $inscripcionId,
                'unidad' => $unidad,
                'score_tareas' => $rawScoreTareas,
                'score_examen' => $scoreExamenFinal,
                'score_conducta' => $scoreConducta,
                'tiene_derecho_examen' => $derechoExamenAnterior,
                'pts_tareas' => $ptsTareas,
                'pts_examen' => $ptsExamen,
                'pts_conducta' => $ptsConducta,
                'promedio_raw' => $promedioRaw
            ]);
            $promedio = $promedioRaw / 10; // Convertir de 0-100 a 0-10

            // 4. Aplicar redondeo especial (0.5 baja, 0.6 sube)
            $trait = new class {
                use CustomRounding;
            };
            $promedioRedondeado = $trait->customRound($promedio, 2);

            // Actualizar los campos calculados
            // CRÍTICO: Si no tiene derecho a examen, score_examen debe ser null
            $calificacion->fill([
                'score_tareas' => $rawScoreTareas,
                'promedio_unidad' => $promedioRedondeado,
                'score_examen' => $scoreExamenParaGuardar, // null si no tiene derecho, valor si tiene derecho
                'score_conducta' => $scoreConducta, // Preservar valor anterior (nunca null)
                'derecho_examen' => $derechoExamenAnterior, // Actualizar según asistencia actual
            ]);
            
            $calificacion->save();

            // Recalcular promedio final de la inscripción
            $inscripcion->promedio_final = $inscripcion->calcularPromedioFinal();
            $inscripcion->aprobado = $inscripcion->promedio_final >= 7.0;
            $inscripcion->save();

            DB::commit();

            return $calificacion->fresh();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Error recalculando promedio unidad: ' . $e->getMessage(), [
                'inscripcion_id' => $inscripcionId,
                'unidad' => $unidad,
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    /**
     * Calcular el score de tareas basado en actividades
     * 
     * @param int $inscripcionId
     * @param int $academicLoadId
     * @param int $unidad
     * @return float Score de 0 a 100
     */
    private static function calculateTareasScore(int $inscripcionId, int $academicLoadId, int $unidad): float
    {
        // Verificar si la tabla de actividades existe
        if (!Schema::hasTable('actividades') || !Schema::hasTable('actividades_entregas')) {
            return 0.0;
        }

        // Obtener el student_id de la inscripción
        $inscripcion = Inscripcion::find($inscripcionId);
        if (!$inscripcion) {
            return 0.0;
        }

        $studentId = $inscripcion->student_id;

        // Obtener todas las actividades de TAREA para esta unidad
        $actividades = Actividad::where('academic_load_id', $academicLoadId)
            ->where('unidad', $unidad)
            ->where('categoria', 'TAREA')
            ->where('activa', true)
            ->get();

        if ($actividades->isEmpty()) {
            return 0.0;
        }

        // Sumar puntos obtenidos y puntos máximos
        $sumaObtenidos = 0;
        $sumaMaximos = 0;

        foreach ($actividades as $actividad) {
            $sumaMaximos += (float) $actividad->valor_maximo;

            // Obtener la entrega del estudiante
            $entrega = ActividadEntrega::where('actividad_id', $actividad->id)
                ->where('student_id', $studentId)
                ->first();

            if ($entrega) {
                $sumaObtenidos += (float) $entrega->calificacion_obtenida;
            }
        }

        // Calcular porcentaje: (suma_obtenidos / suma_maximos) * 100
        if ($sumaMaximos > 0) {
            $porcentaje = ($sumaObtenidos / $sumaMaximos) * 100;
            // Limitar entre 0 y 100
            return max(0, min(100, $porcentaje));
        }

        return 0.0;
    }

    /**
     * Recalcular promedio cuando se actualiza una actividad de tareas
     * 
     * @param int $studentId
     * @param int $academicLoadId
     * @param int $unidad
     * @return CalificacionDetalle
     */
    public static function recalculateFromActivityUpdate(int $studentId, int $academicLoadId, int $unidad): CalificacionDetalle
    {
        // Buscar la inscripción
        $inscripcion = Inscripcion::where('student_id', $studentId)
            ->where('academic_load_id', $academicLoadId)
            ->first();

        if (!$inscripcion) {
            throw new \Exception('Inscripción no encontrada');
        }

        return self::recalculateUnitAverage($inscripcion->id, $unidad);
    }
}


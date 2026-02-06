<?php

namespace App\Services;

use App\Models\Inscripcion;
use App\Models\AcademicPeriod;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class StudentPromotionService
{
    /**
     * Procesar promoción de estudiantes al cerrar un periodo académico
     * 
     * @param AcademicPeriod $closedPeriod El periodo que se está cerrando
     * @param AcademicPeriod|null $nextPeriod El siguiente periodo (se crea si no existe)
     * @return array Resultado del proceso
     */
    public function processPromotion(AcademicPeriod $closedPeriod, ?AcademicPeriod $nextPeriod = null): array
    {
        $results = [
            'promoted' => 0,
            'retained' => 0,
            'errors' => [],
        ];

        try {
            DB::beginTransaction();

            // Obtener todas las inscripciones activas del periodo que se está cerrando
            $inscripciones = Inscripcion::where('academic_period_id', $closedPeriod->id)
                ->where('status', 'cursando')
                ->with(['student', 'calificacionesDetalle'])
                ->get();

            // Agrupar inscripciones por estudiante
            $inscripcionesPorEstudiante = $inscripciones->groupBy('student_id');

            foreach ($inscripcionesPorEstudiante as $studentId => $inscripcionesEstudiante) {
                try {
                    $student = User::find($studentId);
                    if (!$student) {
                        continue;
                    }

                    // Verificar si el estudiante aprobó todas sus materias
                    $aproboTodo = $this->verificarAprobacionCompleta($inscripcionesEstudiante);

                    // Obtener información del estudiante
                    $inscripcionActual = $inscripcionesEstudiante->first();
                    $gradoActual = $this->obtenerGradoActual($inscripcionActual);
                    $carrera = $inscripcionActual->student->studentDetail->carrera ?? null;
                    $grupoActual = $inscripcionActual->grupo ?? null;

                    if (!$carrera) {
                        $results['errors'][] = "Estudiante {$student->name} (ID: {$studentId}) no tiene carrera asignada";
                        continue;
                    }

                    // Actualizar status de las inscripciones del periodo cerrado
                    foreach ($inscripcionesEstudiante as $inscripcion) {
                        // Recalcular promedio final si no está calculado
                        if (!$inscripcion->promedio_final || $inscripcion->promedio_final == 0) {
                            $inscripcion->promedio_final = $inscripcion->calcularPromedioFinal();
                        }

                        // Determinar status final
                        if ($inscripcion->promedio_final >= 7.0) {
                            $inscripcion->status = 'aprobado';
                            $inscripcion->aprobado = true;
                        } else {
                            $inscripcion->status = 'reprobado';
                            $inscripcion->aprobado = false;
                        }
                        $inscripcion->save();
                    }

                    if ($aproboTodo && $nextPeriod) {
                        // El estudiante aprobó todo, promover al siguiente grado
                        $nuevoGrado = $gradoActual + 1;
                        $this->crearInscripcionSiguientePeriodo(
                            $student,
                            $nextPeriod,
                            $carrera,
                            $nuevoGrado,
                            $grupoActual
                        );
                        $results['promoted']++;
                    } else {
                        // El estudiante no aprobó todo, se queda en el mismo grado
                        if ($nextPeriod) {
                            $this->crearInscripcionSiguientePeriodo(
                                $student,
                                $nextPeriod,
                                $carrera,
                                $gradoActual, // Mismo grado
                                $grupoActual
                            );
                        }
                        $results['retained']++;
                    }
                } catch (\Exception $e) {
                    $results['errors'][] = "Error procesando estudiante ID {$studentId}: " . $e->getMessage();
                    Log::error("Error en promoción para estudiante {$studentId}: " . $e->getMessage());
                }
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $results['errors'][] = "Error general en el proceso de promoción: " . $e->getMessage();
            Log::error("Error en proceso de promoción: " . $e->getMessage());
        }

        return $results;
    }

    /**
     * Verificar si un estudiante aprobó todas sus materias
     * 
     * @param \Illuminate\Support\Collection $inscripciones
     * @return bool
     */
    private function verificarAprobacionCompleta($inscripciones): bool
    {
        foreach ($inscripciones as $inscripcion) {
            // Recalcular promedio si no está calculado
            if (!$inscripcion->promedio_final || $inscripcion->promedio_final == 0) {
                $inscripcion->promedio_final = $inscripcion->calcularPromedioFinal();
            }

            // Verificar si aprobó (mínimo 7.0)
            if ($inscripcion->promedio_final < 7.0) {
                return false;
            }
        }

        return true;
    }

    /**
     * Obtener el grado actual del estudiante desde su inscripción
     * 
     * @param Inscripcion $inscripcion
     * @return int
     */
    private function obtenerGradoActual(Inscripcion $inscripcion): int
    {
        // Intentar obtener el grado del cuatrimestre (formato: 2025-1 donde 1 es el grado)
        if ($inscripcion->cuatrimestre) {
            $parts = explode('-', $inscripcion->cuatrimestre);
            if (count($parts) >= 2) {
                $grado = (int) $parts[1];
                if ($grado > 0) {
                    return $grado;
                }
            }
        }

        // Si no se puede obtener del cuatrimestre, intentar del grupo
        if ($inscripcion->student && $inscripcion->student->studentDetail) {
            $gradoStr = $inscripcion->student->studentDetail->grado;
            if ($gradoStr) {
                // Extraer número del grado (ej: "1°", "Primero", etc.)
                preg_match('/(\d+)/', $gradoStr, $matches);
                if (!empty($matches[1])) {
                    return (int) $matches[1];
                }
            }
        }

        // Valor por defecto
        return 1;
    }

    /**
     * Crear inscripción para el siguiente periodo académico
     * 
     * @param User $student
     * @param AcademicPeriod $nextPeriod
     * @param string $carrera
     * @param int $grado
     * @param string|null $grupo
     * @return Inscripcion
     */
    private function crearInscripcionSiguientePeriodo(
        User $student,
        AcademicPeriod $nextPeriod,
        string $carrera,
        int $grado,
        ?string $grupo = null
    ): Inscripcion {
        // Generar cuatrimestre para el siguiente periodo
        $year = date('Y', strtotime($nextPeriod->start_date));
        $cuatrimestre = "{$year}-{$grado}";

        // Buscar o crear grupo para el nuevo grado
        $group = $this->obtenerOCrearGrupo($nextPeriod, $carrera, $grado, $grupo);

        // Crear nueva inscripción
        $inscripcion = Inscripcion::create([
            'student_id' => $student->id,
            'academic_period_id' => $nextPeriod->id,
            'cuatrimestre' => $cuatrimestre,
            'grupo' => $group->grupo,
            'status' => 'cursando',
            'promedio_final' => null,
            'aprobado' => false,
        ]);

        // Actualizar student_detail si existe
        if ($student->studentDetail) {
            $student->studentDetail->update([
                'grado' => (string) $grado,
                'grupo' => $group->grupo,
                'carrera' => $carrera,
            ]);
        }

        return $inscripcion;
    }

    /**
     * Obtener o crear un grupo para el periodo y grado especificados
     * 
     * @param AcademicPeriod $period
     * @param string $carrera
     * @param int $grado
     * @param string|null $grupoPreferido
     * @return Group
     */
    private function obtenerOCrearGrupo(
        AcademicPeriod $period,
        string $carrera,
        int $grado,
        ?string $grupoPreferido = null
    ): Group {
        // Intentar encontrar grupo existente
        $query = Group::where('academic_period_id', $period->id)
            ->where('carrera', $carrera)
            ->where('grado', $grado); // grado es integer

        if ($grupoPreferido) {
            $query->where('grupo', $grupoPreferido);
        }

        $group = $query->first();

        // Si no existe, crear uno nuevo
        if (!$group) {
            $grupo = $grupoPreferido ?? 'A'; // Por defecto grupo A
            $group = Group::create([
                'academic_period_id' => $period->id,
                'carrera' => $carrera,
                'grado' => $grado, // grado es integer
                'grupo' => $grupo,
                'tutor_id' => null,
            ]);
        }

        return $group;
    }
}


<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Inscripcion;

class GradeController extends Controller
{
    /**
     * Obtener calificaciones del estudiante autenticado
     */
    public function index()
    {
        $user = Auth::user();
        $studentDetail = $user->studentDetail;

        if (!$studentDetail) {
            return response()->json([
                'calificaciones' => [],
                'asistencias' => []
            ]);
        }

        // Obtener todas las inscripciones del estudiante
        $inscripciones = Inscripcion::where('student_id', $user->id)
            ->with([
                'academicLoad.subject',
                'calificacionesDetalle' // Obtener todas las calificaciones, tengan promedio o no
            ])
            ->get();

        // Formatear calificaciones
        $calificaciones = [];
        foreach ($inscripciones as $inscripcion) {
            
            // Recorremos todos los detalles, tengan promedio o no
            foreach ($inscripcion->calificacionesDetalle as $calificacion) {
                
                // Usamos isset para evitar el error de columna inexistente
                $promedio = isset($calificacion->promedio_unidad) ? $calificacion->promedio_unidad : null;

                $calificaciones[] = [
                    'id' => $calificacion->id,
                    'materia' => $inscripcion->academicLoad->subject->nombre ?? 'Materia no encontrada',
                    'unidad' => $calificacion->unidad,
                    'score_tareas' => $calificacion->score_tareas !== null ? (float) $calificacion->score_tareas : null,
                    'score_examen' => $calificacion->score_examen !== null ? (float) $calificacion->score_examen : null,
                    'score_conducta' => $calificacion->score_conducta !== null ? (float) $calificacion->score_conducta : null,
                    'promedio_unidad' => $promedio !== null ? (float) $promedio : null,
                    'derecho_examen' => (bool) $calificacion->derecho_examen,
                ];
            }
        }

        // Obtener asistencias del estudiante
        $asistencias = \App\Models\Attendance::where('student_id', $user->id)
            ->with([
                'schedule',
                'justificacion'
            ])
            ->orderBy('fecha', 'desc')
            ->get()
            ->map(function ($attendance) {
                return [
                    'id' => $attendance->id,
                    'fecha' => $attendance->fecha->format('Y-m-d'),
                    'unidad' => $attendance->unidad,
                    'estado' => $attendance->estado,
                    'materia' => $attendance->schedule->materia ?? 'Materia no encontrada',
                    'observacion' => $attendance->observacion,
                    'justificacion' => $attendance->justificacion ? [
                        'id' => $attendance->justificacion->id,
                        'estado' => $attendance->justificacion->estado,
                        'motivo' => $attendance->justificacion->motivo,
                    ] : null,
                ];
            });

        return response()->json([
            'calificaciones' => $calificaciones,
            'asistencias' => $asistencias,
        ]);
    }
}


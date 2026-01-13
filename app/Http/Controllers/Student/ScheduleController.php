<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ScheduleController extends Controller
{
    /**
     * Obtener el horario del grupo del estudiante autenticado
     */
    public function show()
    {
        $user = Auth::user();
        $studentDetail = $user->studentDetail;

        if (!$studentDetail) {
            return response()->json([
                'message' => 'No se encontraron detalles del estudiante'
            ], 404);
        }

        // Obtener carrera y grupo del estudiante
        $carrera = $studentDetail->carrera;
        $grupo = $studentDetail->grupo;
        
        // Si tiene grupo asignado, usar los datos del grupo
        if ($studentDetail->group) {
            $carrera = $studentDetail->group->carrera;
            $grupo = $studentDetail->group->grupo;
        }

        if (!$carrera || !$grupo) {
            return response()->json([
                'message' => 'El estudiante no tiene carrera o grupo asignado',
                'has_schedule' => false
            ]);
        }

        // Buscar horarios del grupo
        $schedules = Schedule::where('carrera', $carrera)
            ->where('grupo', $grupo)
            ->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get();

        if ($schedules->isEmpty()) {
            return response()->json([
                'message' => 'No hay horarios disponibles para este grupo',
                'has_schedule' => false,
                'carrera' => $carrera,
                'grupo' => $grupo
            ]);
        }

        // Organizar horarios por día de la semana
        $horarioOrganizado = [];
        $diasSemana = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes'];

        foreach ($diasSemana as $dia) {
            $horarioOrganizado[$dia] = [];
        }

        foreach ($schedules as $schedule) {
            $dia = $schedule->dia_semana;
            if (isset($horarioOrganizado[$dia])) {
                // Formatear hora correctamente
                $horaInicio = $schedule->hora_inicio instanceof \Carbon\Carbon 
                    ? $schedule->hora_inicio->format('H:i:s') 
                    : (is_string($schedule->hora_inicio) ? $schedule->hora_inicio : date('H:i:s', strtotime($schedule->hora_inicio)));
                
                $horaFin = $schedule->hora_fin instanceof \Carbon\Carbon 
                    ? $schedule->hora_fin->format('H:i:s') 
                    : (is_string($schedule->hora_fin) ? $schedule->hora_fin : date('H:i:s', strtotime($schedule->hora_fin)));
                
                $horarioOrganizado[$dia][] = [
                    'hora_inicio' => $horaInicio,
                    'hora_fin' => $horaFin,
                    'hora' => substr($horaInicio, 0, 5) . '-' . substr($horaFin, 0, 5),
                    'materia' => $schedule->materia,
                    'profesor' => $schedule->profesor,
                    'aula' => $schedule->aula,
                    'tipo' => $schedule->tipo,
                ];
            }
        }

        // Ordenar por hora dentro de cada día
        foreach ($horarioOrganizado as $dia => $horarios) {
            usort($horarioOrganizado[$dia], function($a, $b) {
                return strtotime($a['hora_inicio']) - strtotime($b['hora_inicio']);
            });
        }

        return response()->json([
            'has_schedule' => true,
            'carrera' => $carrera,
            'grupo' => $grupo,
            'horario' => $horarioOrganizado,
            'total_materias' => $schedules->pluck('materia')->unique()->count()
        ]);
    }
}


<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Group;
use App\Models\User;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AttendanceController extends Controller
{
    /**
     * Obtener los grupos del maestro autenticado
     */
    public function getGroups()
    {
        $teacher = Auth::user();
        $teacherName = $teacher->name;

        // Buscar horarios del maestro
        $schedules = Schedule::where('profesor', $teacherName)
            ->select('carrera', 'grupo')
            ->distinct()
            ->get();

        // Agregar información del grupo
        $groups = [];
        foreach ($schedules as $schedule) {
            // Buscar el grupo en la tabla groups
            $group = Group::where('carrera', $schedule->carrera)
                ->where('grupo', $schedule->grupo)
                ->first();

            if ($group) {
                $groups[] = [
                    'id' => $group->id,
                    'carrera' => $group->carrera,
                    'grado' => $group->grado,
                    'grupo' => $group->grupo,
                    'nombre_completo' => "{$group->grado}° - Grupo {$group->grupo} - {$group->carrera}",
                ];
            } else {
                // Si no hay grupo en la tabla groups, usar datos del schedule
                $groups[] = [
                    'id' => null,
                    'carrera' => $schedule->carrera,
                    'grado' => null,
                    'grupo' => $schedule->grupo,
                    'nombre_completo' => "Grupo {$schedule->grupo} - {$schedule->carrera}",
                ];
            }
        }

        // Eliminar duplicados
        $uniqueGroups = collect($groups)->unique(function ($group) {
            return $group['carrera'] . '-' . $group['grupo'];
        })->values();

        return response()->json($uniqueGroups);
    }

    /**
     * Obtener las materias que el maestro imparte en un grupo específico
     */
    public function getSubjects(Request $request)
    {
        $validated = $request->validate([
            'carrera' => 'required|string',
            'grupo' => 'required|string',
        ]);

        $teacher = Auth::user();
        $teacherName = $teacher->name;

        $subjects = Schedule::where('profesor', $teacherName)
            ->where('carrera', $validated['carrera'])
            ->where('grupo', $validated['grupo'])
            ->select('materia', 'id')
            ->distinct()
            ->get();

        return response()->json($subjects);
    }

    /**
     * Obtener la lista de alumnos de un grupo
     */
    public function getStudents(Request $request)
    {
        try {
            $validated = $request->validate([
                'carrera' => 'required|string',
                'grupo' => 'required|string',
                'schedule_id' => 'required|integer|exists:schedules,id',
            ]);

            // Buscar el grupo
            $group = Group::where('carrera', $validated['carrera'])
                ->where('grupo', $validated['grupo'])
                ->first();

            $students = collect();

            if ($group) {
                // Buscar estudiantes del grupo usando group_id
                $students = User::whereHas('studentDetail', function($query) use ($group) {
                        $query->where('group_id', $group->id);
                    })
                    ->with('studentDetail')
                    ->get();
            }

            // Si no se encontraron estudiantes por group_id, buscar por carrera y grupo directamente
            if ($students->isEmpty()) {
                $students = User::whereHas('studentDetail', function($query) use ($validated) {
                        $query->where('carrera', $validated['carrera'])
                              ->where('grupo', $validated['grupo']);
                    })
                    ->with('studentDetail')
                    ->get();
            }

            // Si aún no hay estudiantes, intentar buscar solo por carrera
            if ($students->isEmpty()) {
                $students = User::whereHas('studentDetail', function($query) use ($validated) {
                        $query->where('carrera', $validated['carrera']);
                    })
                    ->with('studentDetail')
                    ->get();
            }

            // Calcular porcentaje de asistencia para cada estudiante
            $studentsWithAttendance = $students->filter(function($student) {
                // Filtrar solo estudiantes que tengan studentDetail
                return $student->studentDetail !== null;
            })->map(function($student) use ($validated) {
                $scheduleId = $validated['schedule_id'];
                
                // Calcular porcentaje de asistencia usando TODOS los registros de asistencia
                $totalClases = 0;
                $presentes = 0;
                $porcentaje = 0;
                
                try {
                    // Asegurar que schedule_id sea integer
                    $scheduleIdInt = (int)$scheduleId;
                    $studentIdInt = (int)$student->id;
                    
                    // Contar TODAS las asistencias registradas para este estudiante en este schedule
                    $totalClases = Attendance::where('schedule_id', $scheduleIdInt)
                        ->where('student_id', $studentIdInt)
                        ->count();
                    
                    // Contar las asistencias donde el estudiante estuvo presente
                    $presentes = Attendance::where('schedule_id', $scheduleIdInt)
                        ->where('student_id', $studentIdInt)
                        ->where('presente', true)
                        ->count();
                    
                    // Calcular porcentaje: (presentes / total) * 100
                    $porcentaje = $totalClases > 0 ? round(($presentes / $totalClases) * 100) : 0;
                    
                    // Asegurar que el porcentaje no exceda 100
                    $porcentaje = min($porcentaje, 100);
                    
                    // Log temporal para depuración (se puede eliminar después)
                    Log::debug('Cálculo de porcentaje de asistencia', [
                        'student_id' => $studentIdInt,
                        'schedule_id' => $scheduleIdInt,
                        'total_clases' => $totalClases,
                        'presentes' => $presentes,
                        'porcentaje' => $porcentaje,
                    ]);
                } catch (\Exception $e) {
                    // Si la tabla attendances no existe aún, usar 0
                    Log::warning('Error calculando porcentaje de asistencia', [
                        'student_id' => $student->id,
                        'schedule_id' => $scheduleId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $porcentaje = 0;
                    $totalClases = 0;
                    $presentes = 0;
                }
                
                return [
                    'id' => (int)$student->id,
                    'matricula' => $student->studentDetail->matricula ?? 'N/A',
                    'nombre' => $student->name,
                    'asistencia' => $porcentaje . '%',
                    'total_clases' => $totalClases,
                    'presentes' => $presentes,
                ];
            })->values();

            // Devolver siempre un array, incluso si está vacío
            return response()->json($studentsWithAttendance->toArray());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error en getStudents: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'error' => 'Error al cargar los alumnos: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar asistencias
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'schedule_id' => 'required|integer|exists:schedules,id',
                'fecha' => 'required|date',
                'asistencias' => 'required|array',
                'asistencias.*.student_id' => 'required|integer|exists:users,id',
                'asistencias.*.presente' => 'required|boolean',
                'asistencias.*.observaciones' => 'nullable|string',
            ]);

            $teacher = Auth::user();
            $schedule = Schedule::findOrFail($validated['schedule_id']);

            // Verificar que el maestro sea el profesor asignado
            if ($schedule->profesor !== $teacher->name) {
                return response()->json([
                    'error' => 'No tienes permiso para tomar asistencia de esta clase'
                ], 403);
            }

            $created = 0;
            $updated = 0;
            $errors = [];

            foreach ($validated['asistencias'] as $index => $asistencia) {
                try {
                    $attendance = Attendance::updateOrCreate(
                        [
                            'student_id' => $asistencia['student_id'],
                            'schedule_id' => $validated['schedule_id'],
                            'fecha' => $validated['fecha'],
                        ],
                        [
                            'presente' => $asistencia['presente'],
                            'observaciones' => $asistencia['observaciones'] ?? null,
                            'teacher_id' => $teacher->id,
                        ]
                    );

                    if ($attendance->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (\Exception $e) {
                    $errors[] = "Error al guardar asistencia del estudiante {$asistencia['student_id']}: " . $e->getMessage();
                    Log::error('Error guardando asistencia individual', [
                        'student_id' => $asistencia['student_id'],
                        'schedule_id' => $validated['schedule_id'],
                        'fecha' => $validated['fecha'],
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            if (count($errors) > 0) {
                return response()->json([
                    'message' => 'Algunas asistencias se guardaron, pero hubo errores',
                    'created' => $created,
                    'updated' => $updated,
                    'errors' => $errors,
                ], 207); // 207 Multi-Status
            }

            return response()->json([
                'message' => 'Asistencias guardadas exitosamente',
                'created' => $created,
                'updated' => $updated,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en store attendances: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'error' => 'Error al guardar asistencias: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener asistencias de una fecha específica
     */
    public function getAttendances(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|integer|exists:schedules,id',
            'fecha' => 'required|date',
        ]);

        $attendances = Attendance::where('schedule_id', $validated['schedule_id'])
            ->where('fecha', $validated['fecha'])
            ->with('student.studentDetail')
            ->get();

        $attendanceMap = [];
        foreach ($attendances as $attendance) {
            // Usar el ID del estudiante como clave (string para consistencia en JSON)
            $attendanceMap[(string)$attendance->student_id] = [
                'presente' => $attendance->presente,
                'observaciones' => $attendance->observaciones,
            ];
        }

        return response()->json($attendanceMap);
    }

    /**
     * Obtener historial de asistencias de un estudiante específico
     */
    public function getStudentHistory(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|integer|exists:users,id',
            'schedule_id' => 'required|integer|exists:schedules,id',
        ]);

        $attendances = Attendance::where('student_id', $validated['student_id'])
            ->where('schedule_id', $validated['schedule_id'])
            ->orderBy('fecha', 'desc')
            ->get();

        $history = $attendances->map(function($attendance) {
            return [
                'fecha' => $attendance->fecha->format('Y-m-d'),
                'fecha_formateada' => $attendance->fecha->format('d/m/Y'),
                'presente' => $attendance->presente,
                'observaciones' => $attendance->observaciones,
            ];
        });

        return response()->json($history);
    }

    /**
     * Obtener historial de asistencias de todos los estudiantes del grupo
     */
    public function getAllStudentsHistory(Request $request)
    {
        $validated = $request->validate([
            'schedule_id' => 'required|integer|exists:schedules,id',
            'carrera' => 'required|string',
            'grupo' => 'required|string',
        ]);

        // Obtener estudiantes del grupo (mismo código que getStudents)
        $group = Group::where('carrera', $validated['carrera'])
            ->where('grupo', $validated['grupo'])
            ->first();

        $students = collect();
        if ($group) {
            $students = User::whereHas('studentDetail', function($query) use ($group) {
                    $query->where('group_id', $group->id);
                })
                ->with('studentDetail')
                ->get();
        }

        if ($students->isEmpty()) {
            $students = User::whereHas('studentDetail', function($query) use ($validated) {
                    $query->where('carrera', $validated['carrera'])
                          ->where('grupo', $validated['grupo']);
                })
                ->with('studentDetail')
                ->get();
        }

        // Obtener todas las asistencias de estos estudiantes para este schedule
        $studentIds = $students->pluck('id');
        $allAttendances = Attendance::where('schedule_id', $validated['schedule_id'])
            ->whereIn('student_id', $studentIds)
            ->with('student.studentDetail')
            ->orderBy('fecha', 'desc')
            ->get();

        // Organizar por estudiante
        $historyByStudent = [];
        foreach ($students as $student) {
            $studentAttendances = $allAttendances->where('student_id', $student->id);
            
            $historyByStudent[] = [
                'student_id' => $student->id,
                'matricula' => $student->studentDetail->matricula ?? 'N/A',
                'nombre' => $student->name,
                'asistencias' => $studentAttendances->map(function($attendance) {
                    return [
                        'fecha' => $attendance->fecha->format('Y-m-d'),
                        'fecha_formateada' => $attendance->fecha->format('d/m/Y'),
                        'presente' => $attendance->presente,
                        'observaciones' => $attendance->observaciones,
                    ];
                })->values(),
                'total_clases' => $studentAttendances->count(),
                'presentes' => $studentAttendances->where('presente', true)->count(),
                'ausentes' => $studentAttendances->where('presente', false)->count(),
                'porcentaje' => $studentAttendances->count() > 0 
                    ? round(($studentAttendances->where('presente', true)->count() / $studentAttendances->count()) * 100)
                    : 0,
            ];
        }

        return response()->json($historyByStudent);
    }
}


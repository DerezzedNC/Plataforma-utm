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
use Illuminate\Support\Facades\Schema;

class AttendanceController extends Controller
{
    /**
     * Obtener los grupos del maestro autenticado
     */
    public function getGroups()
    {
        $teacher = Auth::user();
        $teacherName = $teacher->name;

        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        
        if (!$activePeriod) {
            return response()->json([
                'message' => 'No hay un periodo académico activo. Por favor, contacta al administrador.',
            ], 422);
        }

        // Buscar horarios del maestro del periodo activo (solo donde imparte materias)
        // Usar distinct con múltiples columnas para evitar duplicados
        $schedules = Schedule::where('profesor', $teacherName)
            ->where('academic_period_id', $activePeriod->id)
            ->select('carrera', 'grupo')
            ->distinct()
            ->get();

        // Agregar información del grupo y eliminar duplicados usando un array asociativo
        $groupsMap = [];
        foreach ($schedules as $schedule) {
            // Crear una clave única para evitar duplicados
            $key = strtolower(trim($schedule->carrera)) . '-' . strtolower(trim($schedule->grupo));
            
            // Si ya existe este grupo, saltarlo
            if (isset($groupsMap[$key])) {
                continue;
            }

            // Buscar el grupo en la tabla groups del periodo activo
            $group = Group::where('carrera', $schedule->carrera)
                ->where('grupo', $schedule->grupo)
                ->where('academic_period_id', $activePeriod->id)
                ->first();

            if ($group) {
                $groupsMap[$key] = [
                    'id' => $group->id,
                    'carrera' => $group->carrera,
                    'grado' => $group->grado,
                    'grupo' => $group->grupo,
                    'nombre_completo' => "{$group->grado}° - Grupo {$group->grupo} - {$group->carrera}",
                ];
            } else {
                // Si no hay grupo en la tabla groups, usar datos del schedule
                $groupsMap[$key] = [
                    'id' => null,
                    'carrera' => $schedule->carrera,
                    'grado' => null,
                    'grupo' => $schedule->grupo,
                    'nombre_completo' => "Grupo {$schedule->grupo} - {$schedule->carrera}",
                ];
            }
        }

        // Convertir el mapa a array y ordenar por nombre
        $uniqueGroups = collect(array_values($groupsMap))
            ->sortBy('nombre_completo')
            ->values();

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

        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        
        if (!$activePeriod) {
            return response()->json([
                'message' => 'No hay un periodo académico activo. Por favor, contacta al administrador.',
            ], 422);
        }

        // Obtener materias del maestro para el grupo específico
        $subjects = Schedule::where('profesor', $teacherName)
            ->where('carrera', $validated['carrera'])
            ->where('grupo', $validated['grupo'])
            ->where('academic_period_id', $activePeriod->id)
            ->select('materia', 'id')
            ->get();

        // Eliminar duplicados por materia (por si hay múltiples horarios de la misma materia)
        // Mantener el primer ID encontrado para cada materia
        $uniqueSubjects = $subjects->unique('materia')->map(function($subject) {
            return [
                'id' => $subject->id,
                'materia' => $subject->materia,
            ];
        })->values();

        return response()->json($uniqueSubjects);
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
                    
                    // Verificar si la columna 'estado' existe en la tabla
                    $hasEstadoColumn = Schema::hasColumn('attendances', 'estado');
                    
                    // Contar TODAS las asistencias registradas para este estudiante en este schedule
                    $totalClases = Attendance::where('schedule_id', $scheduleIdInt)
                        ->where('student_id', $studentIdInt)
                        ->count();
                    
                    // Contar las asistencias donde el estudiante estuvo presente
                    if ($hasEstadoColumn) {
                        // Usar la columna 'estado' (nueva estructura)
                        $presentes = Attendance::where('schedule_id', $scheduleIdInt)
                            ->where('student_id', $studentIdInt)
                            ->whereIn('estado', ['presente', 'justificado'])
                            ->count();
                    } else {
                        // Usar la columna 'presente' (estructura antigua)
                        $presentes = Attendance::where('schedule_id', $scheduleIdInt)
                            ->where('student_id', $studentIdInt)
                            ->where('presente', true)
                            ->count();
                    }
                    
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
            $schedule = Schedule::with('period')->findOrFail($validated['schedule_id']);

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
                    // Determinar unidad basada en la fecha (asumiendo que cada periodo tiene 3 unidades)
                    // Esto se puede mejorar más adelante con lógica más precisa
                    $unidad = $this->determinarUnidad($validated['fecha'], $schedule);
                    
                    // Convertir presente (boolean) a estado (enum)
                    // Manejar diferentes tipos de entrada
                    $presenteRaw = $asistencia['presente'] ?? false;
                    
                    // Convertir a booleano de forma explícita
                    if (is_bool($presenteRaw)) {
                        $presenteBool = $presenteRaw;
                    } elseif (is_string($presenteRaw)) {
                        $presenteBool = in_array(strtolower(trim($presenteRaw)), ['true', '1', 'yes', 'on', 'si'], true);
                    } elseif (is_numeric($presenteRaw)) {
                        $presenteBool = (int)$presenteRaw === 1;
                    } else {
                        $presenteBool = (bool)$presenteRaw;
                    }
                    
                    // Verificar si la columna 'estado' existe
                    $hasEstadoColumn = Schema::hasColumn('attendances', 'estado');
                    
                    $estado = $presenteBool ? 'presente' : 'falta';
                    
                    Log::debug('Guardando asistencia', [
                        'student_id' => $asistencia['student_id'],
                        'presente_raw' => $asistencia['presente'],
                        'presente_bool' => $presenteBool,
                        'estado' => $estado,
                        'has_estado_column' => $hasEstadoColumn,
                        'fecha' => $validated['fecha'],
                    ]);
                    
                    // Verificar qué columnas de observaciones existen
                    $hasObservacion = Schema::hasColumn('attendances', 'observacion');
                    $hasObservaciones = Schema::hasColumn('attendances', 'observaciones');
                    
                    // Preparar datos según la estructura de la tabla
                    $attendanceData = [
                        'unidad' => $unidad,
                        'teacher_id' => $teacher->id,
                    ];
                    
                    // Agregar observaciones solo a la columna que existe
                    $observacionesValue = $asistencia['observaciones'] ?? null;
                    if ($hasObservaciones) {
                        $attendanceData['observaciones'] = $observacionesValue;
                    } elseif ($hasObservacion) {
                        $attendanceData['observacion'] = $observacionesValue;
                    }
                    
                    if ($hasEstadoColumn) {
                        $attendanceData['estado'] = $estado;
                    } else {
                        // Usar la columna 'presente' si 'estado' no existe
                        $attendanceData['presente'] = $presenteBool;
                    }
                    
                    $attendance = Attendance::updateOrCreate(
                        [
                            'student_id' => $asistencia['student_id'],
                            'schedule_id' => $validated['schedule_id'],
                            'fecha' => $validated['fecha'],
                        ],
                        $attendanceData
                    );

                    // Verificar que se guardó correctamente
                    $attendance->refresh();
                    $estadoGuardado = $hasEstadoColumn ? $attendance->estado : ($attendance->presente ? 'presente' : 'falta');
                    Log::debug('Asistencia guardada', [
                        'attendance_id' => $attendance->id,
                        'estado_guardado' => $estadoGuardado,
                        'was_recently_created' => $attendance->wasRecentlyCreated,
                    ]);

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
                        'presente' => $asistencia['presente'] ?? 'N/A',
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
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

        $hasEstadoColumn = Schema::hasColumn('attendances', 'estado');
        $attendanceMap = [];
        foreach ($attendances as $attendance) {
            // Usar el ID del estudiante como clave (string para consistencia en JSON)
            // Convertir estado a booleano para compatibilidad con el frontend
            if ($hasEstadoColumn) {
                $presente = in_array($attendance->estado, ['presente', 'justificado']);
                $estado = $attendance->estado;
            } else {
                $presente = $attendance->presente ?? false;
                $estado = $presente ? 'presente' : 'falta';
            }
            $attendanceMap[(string)$attendance->student_id] = [
                'presente' => $presente,
                'estado' => $estado,
                'observaciones' => $attendance->observacion ?? $attendance->observaciones,
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

        $hasEstadoColumn = Schema::hasColumn('attendances', 'estado');
        $history = $attendances->map(function($attendance) use ($hasEstadoColumn) {
            // Convertir estado a booleano para compatibilidad
            if ($hasEstadoColumn) {
                $presente = in_array($attendance->estado, ['presente', 'justificado']);
                $estado = $attendance->estado;
            } else {
                $presente = $attendance->presente ?? false;
                $estado = $presente ? 'presente' : 'falta';
            }
            return [
                'id' => $attendance->id,
                'fecha' => $attendance->fecha->format('Y-m-d'),
                'fecha_formateada' => $attendance->fecha->format('d/m/Y'),
                'presente' => $presente,
                'estado' => $estado,
                'unidad' => $attendance->unidad ?? 1,
                'observaciones' => $attendance->observacion ?? $attendance->observaciones,
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
        $hasEstadoColumn = Schema::hasColumn('attendances', 'estado');
        $allAttendances = Attendance::where('schedule_id', $validated['schedule_id'])
            ->whereIn('student_id', $studentIds)
            ->with('student.studentDetail')
            ->orderBy('fecha', 'desc')
            ->get();

        // Organizar por estudiante
        $historyByStudent = [];
        foreach ($students as $student) {
            $studentAttendances = $allAttendances->where('student_id', $student->id);
            
            if ($hasEstadoColumn) {
                $presentes = $studentAttendances->whereIn('estado', ['presente', 'justificado'])->count();
                $ausentes = $studentAttendances->whereIn('estado', ['falta', 'retardo'])->count();
            } else {
                $presentes = $studentAttendances->where('presente', true)->count();
                $ausentes = $studentAttendances->where('presente', false)->count();
            }
            
            $historyByStudent[] = [
                'student_id' => $student->id,
                'matricula' => $student->studentDetail->matricula ?? 'N/A',
                'nombre' => $student->name,
                'asistencias' => $studentAttendances->map(function($attendance) use ($hasEstadoColumn) {
                    // Convertir estado a booleano para compatibilidad
                    if ($hasEstadoColumn) {
                        $presente = in_array($attendance->estado, ['presente', 'justificado']);
                        $estado = $attendance->estado;
                    } else {
                        $presente = $attendance->presente ?? false;
                        $estado = $presente ? 'presente' : 'falta';
                    }
                    return [
                        'id' => $attendance->id,
                        'fecha' => $attendance->fecha->format('Y-m-d'),
                        'fecha_formateada' => $attendance->fecha->format('d/m/Y'),
                        'presente' => $presente,
                        'estado' => $estado,
                        'unidad' => $attendance->unidad ?? 1,
                        'observaciones' => $attendance->observacion ?? $attendance->observaciones,
                    ];
                })->values(),
                'total_clases' => $studentAttendances->count(),
                'presentes' => $presentes,
                'ausentes' => $ausentes,
                'porcentaje' => $studentAttendances->count() > 0 
                    ? round(($presentes / $studentAttendances->count()) * 100)
                    : 0,
            ];
        }

        return response()->json($historyByStudent);
    }

    /**
     * Determinar la unidad basada en la fecha
     * Asume que cada periodo tiene 3 unidades distribuidas a lo largo del periodo
     */
    private function determinarUnidad($fecha, $schedule)
    {
        // Obtener el periodo académico del schedule
        $period = $schedule->period;
        if (!$period) {
            return 1; // Por defecto unidad 1
        }

        $fechaObj = \Carbon\Carbon::parse($fecha);
        $inicioPeriodo = \Carbon\Carbon::parse($period->start_date);
        $finPeriodo = \Carbon\Carbon::parse($period->end_date);
        
        $duracionTotal = $inicioPeriodo->diffInDays($finPeriodo);
        $diasTranscurridos = $inicioPeriodo->diffInDays($fechaObj);
        
        // Dividir el periodo en 3 partes iguales
        if ($diasTranscurridos <= $duracionTotal / 3) {
            return 1;
        } elseif ($diasTranscurridos <= ($duracionTotal * 2) / 3) {
            return 2;
        } else {
            return 3;
        }
    }

    /**
     * Actualizar una asistencia individual (para corrección/justificación)
     */
    public function updateAttendance(Request $request, string $id)
    {
        try {
            $validated = $request->validate([
                'estado' => 'required|in:justificado',
                'observaciones' => 'nullable|string|max:500',
            ]);

            $attendance = Attendance::with('schedule')->findOrFail($id);
            $teacher = Auth::user();

            // Verificar que el maestro tenga permiso (debe ser el que tomó la asistencia o el profesor del schedule)
            $schedule = $attendance->schedule;
            if (!$schedule) {
                return response()->json([
                    'error' => 'No se encontró el horario asociado a esta asistencia'
                ], 404);
            }

            if ($schedule->profesor !== $teacher->name && $attendance->teacher_id !== $teacher->id) {
                return response()->json([
                    'error' => 'No tienes permiso para modificar esta asistencia'
                ], 403);
            }

            // Verificar si la columna 'estado' existe
            $hasEstadoColumn = Schema::hasColumn('attendances', 'estado');
            
            // Verificar qué columnas de observaciones existen
            $hasObservacion = Schema::hasColumn('attendances', 'observacion');
            $hasObservaciones = Schema::hasColumn('attendances', 'observaciones');
            
            // Preparar datos de actualización
            $updateData = [];
            
            // Agregar observaciones solo a la columna que existe
            $observacionesValue = $validated['observaciones'] ?? null;
            if ($hasObservaciones) {
                $updateData['observaciones'] = $observacionesValue;
            } elseif ($hasObservacion) {
                $updateData['observacion'] = $observacionesValue;
            }
            
            if ($hasEstadoColumn) {
                $updateData['estado'] = $validated['estado'];
            } else {
                // Si no existe 'estado', usar 'presente' y marcar como true cuando es justificado
                $updateData['presente'] = true; // Justificado se considera presente
            }

            // Actualizar la asistencia
            $attendance->update($updateData);
            $attendance->refresh();

            // Determinar el estado para la respuesta
            if ($hasEstadoColumn) {
                $estado = $attendance->estado;
                $presente = in_array($attendance->estado, ['presente', 'justificado']);
            } else {
                $estado = $attendance->presente ? 'justificado' : 'falta';
                $presente = $attendance->presente ?? false;
            }

            return response()->json([
                'message' => 'Asistencia justificada correctamente',
                'attendance' => [
                    'id' => $attendance->id,
                    'fecha' => $attendance->fecha->format('Y-m-d'),
                    'fecha_formateada' => $attendance->fecha->format('d/m/Y'),
                    'presente' => $presente,
                    'estado' => $estado,
                    'unidad' => $attendance->unidad ?? 1,
                    'observaciones' => $attendance->observacion ?? $attendance->observaciones,
                ]
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error actualizando asistencia: ' . $e->getMessage(), [
                'attendance_id' => $id,
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'error' => 'Error al actualizar la asistencia: ' . $e->getMessage()
            ], 500);
        }
    }
}


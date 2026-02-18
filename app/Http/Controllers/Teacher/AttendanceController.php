<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Group;
use App\Models\User;
use App\Models\Attendance;
use App\Models\Subject;
use App\Models\CourseUnit;
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
                'course_unit_id' => 'nullable|integer|exists:course_units,id', // Opcional: si no se envía, se usa la primera unidad
            ]);
            
            // Si no se envía course_unit_id, obtener la primera unidad de la materia
            if (empty($validated['course_unit_id'])) {
                $schedule = Schedule::find($validated['schedule_id']);
                if ($schedule) {
                    $activePeriod = \App\Models\AcademicPeriod::active()->first();
                    if ($activePeriod) {
                        $group = Group::where('carrera', $validated['carrera'])
                            ->where('grupo', $validated['grupo'])
                            ->where('academic_period_id', $activePeriod->id)
                            ->first();
                        
                        if ($group) {
                            $subject = \App\Models\Subject::where('nombre', $schedule->materia)->first();
                            if ($subject) {
                                $academicLoad = \App\Models\AcademicLoad::where('group_id', $group->id)
                                    ->where('subject_id', $subject->id)
                                    ->where('academic_period_id', $activePeriod->id)
                                    ->first();
                                
                                if ($academicLoad) {
                                    $firstUnit = CourseUnit::where('academic_load_id', $academicLoad->id)
                                        ->orderBy('id')
                                        ->first();
                                    
                                    if ($firstUnit) {
                                        $validated['course_unit_id'] = $firstUnit->id;
                                    }
                                }
                            }
                        }
                    }
                }
            }

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
            // Cada unidad debe tener su propio historial, ignorando asistencias antiguas (sin course_unit_id)
            $studentsWithAttendance = $students->filter(function($student) {
                // Filtrar solo estudiantes que tengan studentDetail
                return $student->studentDetail !== null;
            })->map(function($student) use ($validated) {
                $scheduleId = $validated['schedule_id'];
                
                // Calcular porcentaje de asistencia usando SOLO los registros de la unidad especificada
                $totalClases = 0;
                $presentes = 0;
                $porcentaje = 0;
                
                try {
                    // Asegurar que schedule_id sea integer
                    $scheduleIdInt = (int)$scheduleId;
                    $studentIdInt = (int)$student->id;
                    
                    // Verificar si la columna 'estado' existe en la tabla
                    $hasEstadoColumn = Schema::hasColumn('attendances', 'estado');
                    
                    // Verificar si la columna course_unit_id existe
                    $hasCourseUnitIdColumn = Schema::hasColumn('attendances', 'course_unit_id');
                    
                    // Construir query base
                    $query = Attendance::where('schedule_id', $scheduleIdInt)
                        ->where('student_id', $studentIdInt);
                    
                    // Mapear course_unit_id al número de unidad (1, 2, 3, etc.)
                    $numeroUnidad = null;
                    if (!empty($validated['course_unit_id'])) {
                        $courseUnit = CourseUnit::find($validated['course_unit_id']);
                        if ($courseUnit) {
                            // Obtener el orden de la unidad dentro de su academic_load
                            $courseUnits = CourseUnit::where('academic_load_id', $courseUnit->academic_load_id)
                                ->orderBy('id')
                                ->pluck('id')
                                ->toArray();
                            $numeroUnidad = array_search($validated['course_unit_id'], $courseUnits) + 1; // +1 porque array_search es 0-based
                        }
                    }
                    
                    // FILTRO OBLIGATORIO POR UNIDAD: Usar la columna 'unidad' de la base de datos
                    if (empty($numeroUnidad)) {
                        // Si no se puede determinar la unidad, devolver 0%
                        $totalClases = 0;
                        $presentes = 0;
                        $porcentaje = 0;
                    } else {
                        // FÓRMULA CORRECTA: Contar fechas DISTINTAS de clases para esta unidad
                        // Total clases de la unidad = fechas distintas donde hay asistencias registradas
                        // Para SQLite, usar DB::raw con COUNT(DISTINCT fecha)
                        $totalClases = Attendance::where('schedule_id', $scheduleIdInt)
                            ->where('unidad', $numeroUnidad)
                            ->select(DB::raw('COUNT(DISTINCT fecha) as total'))
                            ->value('total') ?? 0;
                        
                        // Si no hay clases registradas para esta unidad, devolver 0%
                        if ($totalClases == 0) {
                            $porcentaje = 0;
                            $presentes = 0;
                        } else {
                            // Contar las asistencias donde el estudiante estuvo presente en esta unidad
                            // El estado se guarda exactamente como el string "presente"
                            if ($hasEstadoColumn) {
                                $presentes = Attendance::where('schedule_id', $scheduleIdInt)
                                    ->where('student_id', $studentIdInt)
                                    ->where('unidad', $numeroUnidad)
                                    ->where('estado', 'presente') // Exactamente "presente", no whereIn
                                    ->count();
                            } else {
                                $presentes = Attendance::where('schedule_id', $scheduleIdInt)
                                    ->where('student_id', $studentIdInt)
                                    ->where('unidad', $numeroUnidad)
                                    ->where('presente', true)
                                    ->count();
                            }
                            
                            // Calcular porcentaje: (presentes / total) * 100
                            $porcentaje = $totalClases > 0 ? round(($presentes / $totalClases) * 100) : 0;
                            $porcentaje = min($porcentaje, 100);
                        }
                        
                        // Log detallado para depuración
                        Log::info('Cálculo de porcentaje de asistencia por unidad', [
                            'student_id' => $studentIdInt,
                            'schedule_id' => $scheduleIdInt,
                            'course_unit_id' => $validated['course_unit_id'] ?? null,
                            'numero_unidad' => $numeroUnidad,
                            'total_clases' => $totalClases,
                            'presentes' => $presentes,
                            'porcentaje_calculado' => $porcentaje,
                            'has_estado_column' => $hasEstadoColumn,
                        ]);
                    }
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

            // Obtener el schedule para encontrar el academic_load_id
            $schedule = Schedule::find($validated['schedule_id']);
            $courseUnits = collect([]);
            
            if ($schedule) {
                // Buscar el academic_load usando carrera, grupo y materia
                $activePeriod = \App\Models\AcademicPeriod::active()->first();
                if ($activePeriod) {
                    // Buscar el grupo
                    $group = Group::where('carrera', $validated['carrera'])
                        ->where('grupo', $validated['grupo'])
                        ->where('academic_period_id', $activePeriod->id)
                        ->first();
                    
                    if ($group) {
                        // Buscar la materia
                        $subject = Subject::where('nombre', $schedule->materia)->first();
                        
                        if ($subject) {
                            // Buscar el academic_load
                            $academicLoad = \App\Models\AcademicLoad::where('group_id', $group->id)
                                ->where('subject_id', $subject->id)
                                ->where('academic_period_id', $activePeriod->id)
                                ->first();
                            
                            if ($academicLoad) {
                                // Obtener las unidades del curso
                                $courseUnits = CourseUnit::where('academic_load_id', $academicLoad->id)
                                    ->orderBy('id')
                                    ->get();
                            }
                        }
                    }
                }
            }

            // Devolver siempre un array, incluso si está vacío, incluyendo las unidades
            return response()->json([
                'students' => $studentsWithAttendance->toArray(),
                'course_units' => $courseUnits->map(function($unit) {
                    return [
                        'id' => $unit->id,
                        'nombre' => $unit->nombre,
                        'porcentaje' => $unit->porcentaje,
                    ];
                })->toArray(),
            ]);
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
                'course_unit_id' => 'required|integer|exists:course_units,id',
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
                    // Mapear course_unit_id al número de unidad (1, 2, 3, etc.)
                    // Si se envía course_unit_id, usarlo; si no, calcular por fecha
                    $unidad = null;
                    if (!empty($validated['course_unit_id'])) {
                        $courseUnit = CourseUnit::find($validated['course_unit_id']);
                        if ($courseUnit) {
                            // Obtener el orden de la unidad dentro de su academic_load
                            $courseUnits = CourseUnit::where('academic_load_id', $courseUnit->academic_load_id)
                                ->orderBy('id')
                                ->pluck('id')
                                ->toArray();
                            $unidad = array_search($validated['course_unit_id'], $courseUnits) + 1; // +1 porque array_search es 0-based
                        }
                    }
                    
                    // Si no se pudo determinar por course_unit_id, usar el método antiguo por fecha
                    if (empty($unidad)) {
                        $unidad = $this->determinarUnidad($validated['fecha'], $schedule);
                    }
                    
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
                    
                    // Verificar si la columna course_unit_id existe antes de intentar guardarla
                    $hasCourseUnitIdColumn = Schema::hasColumn('attendances', 'course_unit_id');
                    
                    // Preparar datos según la estructura de la tabla
                    $attendanceData = [
                        'unidad' => $unidad,
                        'teacher_id' => $teacher->id,
                    ];
                    
                    // Solo agregar course_unit_id si la columna existe
                    if ($hasCourseUnitIdColumn) {
                        $attendanceData['course_unit_id'] = $validated['course_unit_id'];
                    } else {
                        // Si la columna no existe, registrar un warning pero continuar
                        Log::warning('Intentando guardar course_unit_id pero la columna no existe en la tabla attendances', [
                            'course_unit_id' => $validated['course_unit_id'],
                            'student_id' => $asistencia['student_id'],
                            'schedule_id' => $validated['schedule_id'],
                        ]);
                    }
                    
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
                    
                    // Si la columna course_unit_id existe, incluirla en las claves de búsqueda
                    // para evitar actualizar registros de otras unidades
                    $searchKeys = [
                        'student_id' => $asistencia['student_id'],
                        'schedule_id' => $validated['schedule_id'],
                        'fecha' => $validated['fecha'],
                    ];
                    
                    // Si course_unit_id existe, incluirlo en las claves de búsqueda
                    if ($hasCourseUnitIdColumn && !empty($validated['course_unit_id'])) {
                        $searchKeys['course_unit_id'] = $validated['course_unit_id'];
                    }
                    
                    $attendance = Attendance::updateOrCreate(
                        $searchKeys,
                        $attendanceData
                    );

                    // Verificar que se guardó correctamente
                    $attendance->refresh();
                    $estadoGuardado = $hasEstadoColumn ? $attendance->estado : ($attendance->presente ? 'presente' : 'falta');
                    
                    // Verificar que el course_unit_id se guardó correctamente
                    $courseUnitIdGuardado = $hasCourseUnitIdColumn ? ($attendance->course_unit_id ?? 'NULL') : 'N/A (columna no existe)';
                    
                    Log::info('Asistencia guardada', [
                        'attendance_id' => $attendance->id,
                        'student_id' => $asistencia['student_id'],
                        'schedule_id' => $validated['schedule_id'],
                        'fecha' => $validated['fecha'],
                        'course_unit_id_enviado' => $validated['course_unit_id'] ?? 'NULL',
                        'course_unit_id_guardado' => $courseUnitIdGuardado,
                        'estado_guardado' => $estadoGuardado,
                        'was_recently_created' => $attendance->wasRecentlyCreated,
                        'search_keys' => $searchKeys,
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
        try {
            $validated = $request->validate([
                'schedule_id' => 'required|integer|exists:schedules,id',
                'fecha' => 'required|date',
                'course_unit_id' => 'nullable|integer|exists:course_units,id',
            ]);

        // Verificar si la columna course_unit_id existe
        $hasCourseUnitIdColumn = Schema::hasColumn('attendances', 'course_unit_id');

        $query = Attendance::where('schedule_id', $validated['schedule_id'])
            ->where('fecha', $validated['fecha']);
        
        // Si se especifica course_unit_id y la columna existe, filtrar SOLO por esa unidad
        // IGNORAR asistencias antiguas (sin course_unit_id)
        if (!empty($validated['course_unit_id']) && $hasCourseUnitIdColumn) {
            $courseUnitId = (int)$validated['course_unit_id'];
            // Solo mostrar asistencias de la unidad especificada, ignorar las sin course_unit_id
            $query->where('course_unit_id', $courseUnitId);
        } elseif ($hasCourseUnitIdColumn) {
            // Si la columna existe pero no se especifica course_unit_id, solo mostrar las que tengan course_unit_id
            $query->whereNotNull('course_unit_id');
        }
        // Si la columna no existe, mostrar todas (comportamiento antiguo para compatibilidad)
            
            $attendances = $query->with('student.studentDetail')
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
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error en getAttendances: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            return response()->json([
                'error' => 'Error al obtener asistencias: ' . $e->getMessage()
            ], 500);
        }
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


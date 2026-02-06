<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Schedule;
use App\Models\Group;
use App\Models\Subject;
use App\Models\Career;
use App\Models\Area;
use App\Models\User;
use App\Models\AcademicLoad;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class ScheduleController extends Controller
{
    /**
     * Listar todos los horarios
     */
    public function index(Request $request)
    {
        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        
        if (!$activePeriod) {
            return response()->json([
                'message' => 'No hay un periodo académico activo. Por favor, contacta al administrador.',
            ], 422);
        }

        $query = Schedule::where('academic_period_id', $activePeriod->id);

        // Filtros opcionales
        if ($request->has('carrera')) {
            $query->where('carrera', $request->carrera);
        }

        if ($request->has('grupo')) {
            $query->where('grupo', $request->grupo);
        }

        if ($request->has('grado')) {
            // Buscar grupos con ese grado y carrera del periodo activo
            $groups = Group::where('grado', $request->grado)
                ->where('carrera', $request->carrera ?? '')
                ->where('academic_period_id', $activePeriod->id)
                ->pluck('grupo');
            $query->whereIn('grupo', $groups);
        }

        $schedules = $query->orderBy('dia_semana')
            ->orderBy('hora_inicio')
            ->get();

        return response()->json($schedules);
    }

    /**
     * Obtener grupos disponibles por carrera y grado
     */
    public function getGroups(Request $request)
    {
        $validated = $request->validate([
            'carrera' => 'required|string',
            'grado' => 'required|integer|min:1|max:5',
        ]);

        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        
        if (!$activePeriod) {
            return response()->json([
                'message' => 'No hay un periodo académico activo. Por favor, contacta al administrador.',
            ], 422);
        }

        $groups = Group::where('carrera', $validated['carrera'])
            ->where('grado', $validated['grado'])
            ->where('academic_period_id', $activePeriod->id)
            ->orderBy('grupo')
            ->get();

        return response()->json($groups);
    }

    /**
     * Obtener materias disponibles por carrera y grado
     * Las materias se comparten entre todos los grupos del mismo grado y carrera
     * Ejemplo: Si 1ro de Mecánica (grupos A, B, C) llevan Matemáticas,
     * todos los grupos de 1ro de Mecánica tendrán la misma materia de Matemáticas
     */
    public function getSubjects(Request $request)
    {
        $validated = $request->validate([
            'carrera' => 'required|string',
            'grado' => 'required|integer|min:1|max:5',
            'grupo' => 'nullable|string', // Opcional: el grupo no afecta las materias disponibles
        ]);

        // Buscar la carrera por nombre
        $career = Career::where('nombre', $validated['carrera'])->first();
        
        if (!$career) {
            return response()->json([]);
        }

        // Obtener materias del grado y carrera
        // IMPORTANTE: Las materias se comparten entre todos los grupos del mismo grado y carrera
        // El parámetro 'grupo' se ignora porque las materias son las mismas para todos los grupos
        $subjects = Subject::where('grado', $validated['grado'])
            ->whereHas('careers', function($q) use ($career) {
                $q->where('careers.id', $career->id);
            })
            ->orderBy('nombre')
            ->get();

        return response()->json($subjects);
    }

    /**
     * Validar conflictos de horarios (maestro o aula ocupados)
     */
    public function checkConflicts(Request $request)
    {
        $validated = $request->validate([
            'profesor' => 'required|string',
            'aula' => 'required|string',
            'dia_semana' => 'required|string',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i',
            'exclude_id' => 'nullable|integer', // Para excluir el mismo horario al editar
        ]);

        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        
        if (!$activePeriod) {
            return response()->json([
                'message' => 'No hay un periodo académico activo. Por favor, contacta al administrador.',
            ], 422);
        }

        $conflicts = [];

        // Verificar conflictos con el maestro (solo del periodo activo)
        $teacherConflicts = Schedule::where('profesor', $validated['profesor'])
            ->where('academic_period_id', $activePeriod->id)
            ->where('dia_semana', $validated['dia_semana'])
            ->where(function($q) use ($validated) {
                $q->whereBetween('hora_inicio', [$validated['hora_inicio'], $validated['hora_fin']])
                  ->orWhereBetween('hora_fin', [$validated['hora_inicio'], $validated['hora_fin']])
                  ->orWhere(function($q2) use ($validated) {
                      $q2->where('hora_inicio', '<=', $validated['hora_inicio'])
                         ->where('hora_fin', '>=', $validated['hora_fin']);
                  });
            })
            ->when($request->has('exclude_id'), function($q) use ($request) {
                $q->where('id', '!=', $request->exclude_id);
            })
            ->get();

        if ($teacherConflicts->count() > 0) {
            $conflicts['profesor'] = $teacherConflicts;
        }

        // Verificar conflictos con el aula (solo del periodo activo)
        $roomConflicts = Schedule::where('aula', $validated['aula'])
            ->where('academic_period_id', $activePeriod->id)
            ->where('dia_semana', $validated['dia_semana'])
            ->where(function($q) use ($validated) {
                $q->whereBetween('hora_inicio', [$validated['hora_inicio'], $validated['hora_fin']])
                  ->orWhereBetween('hora_fin', [$validated['hora_inicio'], $validated['hora_fin']])
                  ->orWhere(function($q2) use ($validated) {
                      $q2->where('hora_inicio', '<=', $validated['hora_inicio'])
                         ->where('hora_fin', '>=', $validated['hora_fin']);
                  });
            })
            ->when($request->has('exclude_id'), function($q) use ($request) {
                $q->where('id', '!=', $request->exclude_id);
            })
            ->get();

        if ($roomConflicts->count() > 0) {
            $conflicts['aula'] = $roomConflicts;
        }

        return response()->json([
            'has_conflicts' => count($conflicts) > 0,
            'conflicts' => $conflicts
        ]);
    }

    /**
     * Verificar asignación de maestro para un grupo y materia
     * Busca primero en academic_loads, luego en horarios existentes
     */
    public function checkAssignment(Request $request, $group, $subject)
    {
        try {
            $groupModel = Group::findOrFail($group);
            $subjectModel = Subject::findOrFail($subject);

            // Primero: Verificar en academic_loads si existe
            if (Schema::hasTable('academic_loads')) {
                // Obtener el periodo académico activo
                $activePeriod = \App\Models\AcademicPeriod::active()->first();
                
                $query = AcademicLoad::where('group_id', $groupModel->id)
                    ->where('subject_id', $subjectModel->id);
                
                // Filtrar por periodo activo si existe
                if ($activePeriod) {
                    $query->where('academic_period_id', $activePeriod->id);
                }
                
                $academicLoad = $query->first();

                if ($academicLoad && $academicLoad->teacher_name) {
                    \Log::info('Asignación encontrada en academic_loads', [
                        'group_id' => $groupModel->id,
                        'subject_id' => $subjectModel->id,
                        'teacher_name' => $academicLoad->teacher_name,
                    ]);
                    return response()->json([
                        'teacher_name' => $academicLoad->teacher_name,
                        'has_assignment' => true,
                        'source' => 'academic_loads'
                    ]);
                }
            }

            // Segundo: Buscar en horarios existentes para el mismo grupo y materia
            // Si el grupo ya tiene esta materia asignada en otro día, usar el mismo maestro
            $existingSchedule = Schedule::where('carrera', $groupModel->carrera)
                ->where('grupo', $groupModel->grupo)
                ->where('materia', $subjectModel->nombre)
                ->whereNotNull('profesor')
                ->where('profesor', '!=', '')
                ->first();

            if ($existingSchedule) {
                \Log::info('Asignación encontrada en horarios existentes', [
                    'group_id' => $groupModel->id,
                    'subject_id' => $subjectModel->id,
                    'teacher_name' => $existingSchedule->profesor,
                    'schedule_id' => $existingSchedule->id,
                    'day' => $existingSchedule->dia_semana,
                ]);
                return response()->json([
                    'teacher_name' => $existingSchedule->profesor,
                    'has_assignment' => true,
                    'source' => 'existing_schedule'
                ]);
            }

            \Log::info('No se encontró asignación en checkAssignment', [
                'group_id' => $groupModel->id,
                'subject_id' => $subjectModel->id,
                'carrera' => $groupModel->carrera,
                'grupo' => $groupModel->grupo,
                'materia' => $subjectModel->nombre,
            ]);

            return response()->json([
                'teacher_name' => null,
                'has_assignment' => false
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en checkAssignment', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'group' => $group,
                'subject' => $subject,
            ]);
            return response()->json([
                'teacher_name' => null,
                'has_assignment' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear un nuevo horario
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'carrera' => 'required|string',
            'grupo' => 'required|string|in:A,B,C,D,E',
            'materia' => 'required|string',
            'profesor' => 'required|string',
            'dia_semana' => 'required|string',
            'hora_inicio' => 'required|date_format:H:i',
            'hora_fin' => 'required|date_format:H:i|after:hora_inicio',
            'aula' => 'required|string',
            'tipo' => 'nullable|string|in:Teoría,Laboratorio,Práctica',
            'group_id' => 'nullable|exists:groups,id',
            'subject_id' => 'nullable|exists:subjects,id',
        ]);

        // Asignar 'Teoría' por defecto si no se proporciona tipo
        if (!isset($validated['tipo']) || empty($validated['tipo'])) {
            $validated['tipo'] = 'Teoría';
        }

        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        
        if (!$activePeriod) {
            return response()->json([
                'message' => 'No hay un periodo académico activo. Por favor, contacta al administrador.',
            ], 422);
        }

        $validated['academic_period_id'] = $activePeriod->id;
        $schedule = Schedule::create($validated);

        // Actualizar todos los horarios existentes del mismo grupo y materia con el mismo maestro
        // para mantener la consistencia (un solo maestro por materia por grupo)
        // Solo del periodo activo
        $existingSchedules = Schedule::where('carrera', $validated['carrera'])
            ->where('grupo', $validated['grupo'])
            ->where('materia', $validated['materia'])
            ->where('academic_period_id', $activePeriod->id)
            ->where('id', '!=', $schedule->id)
            ->where(function($q) use ($validated) {
                $q->whereNull('profesor')
                  ->orWhere('profesor', '!=', $validated['profesor']);
            })
            ->get();

        if ($existingSchedules->count() > 0) {
            foreach ($existingSchedules as $existingSchedule) {
                $existingSchedule->update(['profesor' => $validated['profesor']]);
            }
            \Log::info('Horarios actualizados automáticamente para mantener consistencia', [
                'carrera' => $validated['carrera'],
                'grupo' => $validated['grupo'],
                'materia' => $validated['materia'],
                'profesor' => $validated['profesor'],
                'updated_count' => $existingSchedules->count(),
            ]);
        }

        // Guardar o actualizar la asignación en academic_loads
        if ($request->has('group_id') && $request->has('subject_id') && $request->group_id && $request->subject_id) {
            try {
                // Verificar si la tabla existe antes de intentar usarla
                if (Schema::hasTable('academic_loads')) {
                    // Obtener el periodo académico activo
                    $activePeriod = \App\Models\AcademicPeriod::active()->first();
                    
                    if (!$activePeriod) {
                        \Log::warning('No hay periodo académico activo al guardar academic_load');
                    }
                    
                    $academicLoad = AcademicLoad::updateOrCreate(
                        [
                            'group_id' => $request->group_id,
                            'subject_id' => $request->subject_id,
                            'academic_period_id' => $activePeriod ? $activePeriod->id : null,
                        ],
                        [
                            'teacher_name' => $validated['profesor'],
                            'academic_period_id' => $activePeriod ? $activePeriod->id : null,
                        ]
                    );
                    
                    \Log::info('Academic Load guardado/actualizado', [
                        'group_id' => $request->group_id,
                        'subject_id' => $request->subject_id,
                        'teacher_name' => $validated['profesor'],
                        'academic_load_id' => $academicLoad->id,
                    ]);
                } else {
                    \Log::warning('Tabla academic_loads no existe. Ejecuta: php artisan migrate');
                }
            } catch (\Exception $e) {
                // Si hay error (tabla no existe, etc.), solo loguear pero no fallar
                \Log::error('Error guardando academic_load', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'group_id' => $request->group_id ?? null,
                    'subject_id' => $request->subject_id ?? null,
                ]);
            }
        } else {
            \Log::warning('No se guardó academic_load - Faltan datos', [
                'has_group_id' => $request->has('group_id'),
                'has_subject_id' => $request->has('subject_id'),
                'group_id' => $request->group_id ?? null,
                'subject_id' => $request->subject_id ?? null,
            ]);
        }

        return response()->json($schedule, 201);
    }

    /**
     * Crear horarios en lote
     */
    public function storeBatch(Request $request)
    {
        $validated = $request->validate([
            'horarios' => 'required|array',
            'horarios.*.carrera' => 'required|string',
            'horarios.*.grupo' => 'required|string|in:A,B,C,D,E',
            'horarios.*.materia' => 'required|string',
            'horarios.*.profesor' => 'required|string',
            'horarios.*.dia_semana' => 'required|string',
            'horarios.*.hora_inicio' => 'required|date_format:H:i',
            'horarios.*.hora_fin' => 'required|date_format:H:i',
            'horarios.*.aula' => 'required|string',
            'horarios.*.tipo' => 'required|string|in:Teoría,Laboratorio,Práctica',
        ]);

        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        
        if (!$activePeriod) {
            return response()->json([
                'message' => 'No hay un periodo académico activo. Por favor, contacta al administrador.',
            ], 422);
        }

        $schedules = [];

        foreach ($validated['horarios'] as $horario) {
            $horario['academic_period_id'] = $activePeriod->id;
            $schedules[] = Schedule::create($horario);
        }

        return response()->json($schedules, 201);
    }

    /**
     * Actualizar un horario
     */
    public function update(Request $request, string $schedule)
    {
        $scheduleModel = Schedule::findOrFail($schedule);
        
        $validated = $request->validate([
            'carrera' => 'sometimes|string',
            'grupo' => 'sometimes|string|in:A,B,C,D,E',
            'materia' => 'sometimes|string',
            'profesor' => 'sometimes|string',
            'dia_semana' => 'sometimes|string',
            'hora_inicio' => 'sometimes|date_format:H:i',
            'hora_fin' => 'sometimes|date_format:H:i',
            'aula' => 'sometimes|string',
            'tipo' => 'nullable|string|in:Teoría,Laboratorio,Práctica',
            'group_id' => 'nullable|exists:groups,id',
            'subject_id' => 'nullable|exists:subjects,id',
        ]);

        // Asignar 'Teoría' por defecto si no se proporciona tipo
        if (!isset($validated['tipo']) || empty($validated['tipo'])) {
            $validated['tipo'] = $scheduleModel->tipo ?? 'Teoría';
        }

        $scheduleModel->update($validated);

        // Si se actualizó el maestro, actualizar todos los horarios existentes del mismo grupo y materia
        // para mantener la consistencia (un solo maestro por materia por grupo)
        // Solo del periodo activo
        if ($request->has('profesor') && $validated['profesor']) {
            $carrera = $validated['carrera'] ?? $scheduleModel->carrera;
            $grupo = $validated['grupo'] ?? $scheduleModel->grupo;
            $materia = $validated['materia'] ?? $scheduleModel->materia;
            
            // Obtener el periodo académico activo
            $activePeriod = \App\Models\AcademicPeriod::active()->first();
            
            $existingSchedules = Schedule::where('carrera', $carrera)
                ->where('grupo', $grupo)
                ->where('materia', $materia)
                ->where('academic_period_id', $activePeriod ? $activePeriod->id : $scheduleModel->academic_period_id)
                ->where('id', '!=', $scheduleModel->id)
                ->where(function($q) use ($validated) {
                    $q->whereNull('profesor')
                      ->orWhere('profesor', '!=', $validated['profesor']);
                })
                ->get();

            if ($existingSchedules->count() > 0) {
                foreach ($existingSchedules as $existingSchedule) {
                    $existingSchedule->update(['profesor' => $validated['profesor']]);
                }
                \Log::info('Horarios actualizados automáticamente al editar para mantener consistencia', [
                    'schedule_id' => $scheduleModel->id,
                    'carrera' => $carrera,
                    'grupo' => $grupo,
                    'materia' => $materia,
                    'profesor' => $validated['profesor'],
                    'updated_count' => $existingSchedules->count(),
                ]);
            }
        }

        // Actualizar la asignación en academic_loads si se cambió el maestro
        if ($request->has('group_id') && $request->has('subject_id') && $request->has('profesor') && $request->group_id && $request->subject_id) {
            try {
                // Verificar si la tabla existe antes de intentar usarla
                if (Schema::hasTable('academic_loads')) {
                    // Obtener el periodo académico activo
                    $activePeriod = \App\Models\AcademicPeriod::active()->first();
                    
                    if (!$activePeriod) {
                        \Log::warning('No hay periodo académico activo al actualizar academic_load');
                    }
                    
                    $academicLoad = AcademicLoad::updateOrCreate(
                        [
                            'group_id' => $request->group_id,
                            'subject_id' => $request->subject_id,
                            'academic_period_id' => $activePeriod ? $activePeriod->id : null,
                        ],
                        [
                            'teacher_name' => $validated['profesor'],
                            'academic_period_id' => $activePeriod ? $activePeriod->id : null,
                        ]
                    );
                    
                    \Log::info('Academic Load actualizado', [
                        'schedule_id' => $schedule,
                        'group_id' => $request->group_id,
                        'subject_id' => $request->subject_id,
                        'teacher_name' => $validated['profesor'],
                        'academic_load_id' => $academicLoad->id,
                    ]);
                } else {
                    \Log::warning('Tabla academic_loads no existe. Ejecuta: php artisan migrate');
                }
            } catch (\Exception $e) {
                // Si hay error (tabla no existe, etc.), solo loguear pero no fallar
                \Log::error('Error actualizando academic_load', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'schedule_id' => $schedule,
                    'group_id' => $request->group_id ?? null,
                    'subject_id' => $request->subject_id ?? null,
                ]);
            }
        } else {
            \Log::warning('No se actualizó academic_load - Faltan datos', [
                'schedule_id' => $schedule,
                'has_group_id' => $request->has('group_id'),
                'has_subject_id' => $request->has('subject_id'),
                'has_profesor' => $request->has('profesor'),
                'group_id' => $request->group_id ?? null,
                'subject_id' => $request->subject_id ?? null,
            ]);
        }

        return response()->json($scheduleModel);
    }

    /**
     * Eliminar un horario
     */
    public function destroy(string $schedule)
    {
        $scheduleModel = Schedule::findOrFail($schedule);
        $scheduleModel->delete();

        return response()->json(['message' => 'Horario eliminado correctamente']);
    }
}

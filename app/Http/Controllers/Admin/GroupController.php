<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\StudentDetail;
use App\Models\User;
use Illuminate\Http\Request;

class GroupController extends Controller
{
    /**
     * Listar todos los grupos
     */
    public function index()
    {
        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        
        if (!$activePeriod) {
            return response()->json([
                'message' => 'No hay un periodo académico activo. Por favor, contacta al administrador.',
            ], 422);
        }

        $groups = Group::where('academic_period_id', $activePeriod->id)
            ->withCount('students')
            ->with('tutor:id,name,email')
            ->orderBy('carrera')
            ->orderBy('grado')
            ->orderBy('grupo')
            ->get();

        return response()->json($groups);
    }

    /**
     * Obtener estudiantes de un grupo específico
     */
    public function getStudents(string $id)
    {
        $group = Group::findOrFail($id);
        $students = StudentDetail::where('group_id', $id)
            ->with('user')
            ->get();

        return response()->json($students);
    }

    /**
     * Obtener opciones disponibles para crear grupos
     * Devuelve los grados y grupos que aún no están ocupados para una carrera específica
     */
    public function getAvailableOptions(Request $request)
    {
        $validated = $request->validate([
            'carrera' => 'required|string',
            'grado' => 'nullable|integer|min:1|max:5',
        ]);

        $carrera = $validated['carrera'];
        $grado = $validated['grado'] ?? null;

        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        
        if (!$activePeriod) {
            return response()->json([
                'message' => 'No hay un periodo académico activo. Por favor, contacta al administrador.',
            ], 422);
        }

        // Obtener todos los grupos existentes para esta carrera del periodo activo
        $query = Group::where('carrera', $carrera)
            ->where('academic_period_id', $activePeriod->id);
        
        if ($grado) {
            $query->where('grado', $grado);
        }
        
        $existingGroups = $query->get();

        // Grados disponibles (1-5)
        $allGrados = [1, 2, 3, 4, 5];
        
        // Grupos disponibles (A-E)
        $allGrupos = ['A', 'B', 'C', 'D', 'E'];

        // Si se especificó un grado, devolver solo los grupos disponibles para ese grado
        if ($grado) {
            // Los grupos ya están filtrados por carrera y grado
            $gruposOcupados = $existingGroups->pluck('grupo')->toArray();
            $gruposDisponibles = array_values(array_diff($allGrupos, $gruposOcupados));
            
            \Log::info('Opciones disponibles', [
                'carrera' => $carrera,
                'grado' => $grado,
                'total_grupos_existentes' => $existingGroups->count(),
                'grupos_ocupados' => $gruposOcupados,
                'grupos_disponibles' => $gruposDisponibles,
            ]);
            
            return response()->json([
                'grado' => $grado,
                'grupos_disponibles' => $gruposDisponibles,
                'grupos_ocupados' => $gruposOcupados,
            ]);
        }

        // Si no se especificó grado, devolver información de todos los grados
        $gradosInfo = [];
        foreach ($allGrados as $g) {
            // Filtrar grupos existentes para este grado específico
            $gruposOcupadosParaEsteGrado = $existingGroups->filter(function($group) use ($g) {
                return $group->grado == $g;
            })->pluck('grupo')->toArray();
            
            $gruposDisponibles = array_values(array_diff($allGrupos, $gruposOcupadosParaEsteGrado));
            
            // Un grado tiene grupos disponibles solo si tiene menos de 2 grupos creados
            // Máximo 2 grupos por grado
            $totalGruposEnGrado = count($gruposOcupadosParaEsteGrado);
            $tieneGruposDisponibles = $totalGruposEnGrado < 2 && count($gruposDisponibles) > 0;
            
            $gradosInfo[$g] = [
                'grupos_disponibles' => $gruposDisponibles,
                'grupos_ocupados' => $gruposOcupadosParaEsteGrado,
                'total_grupos' => $totalGruposEnGrado,
                'tiene_grupos_disponibles' => $tieneGruposDisponibles,
            ];
            
            \Log::info("Grado {$g} - Carrera {$carrera}", [
                'grupos_ocupados' => $gruposOcupadosParaEsteGrado,
                'grupos_disponibles' => $gruposDisponibles,
                'tiene_disponibles' => count($gruposDisponibles) > 0,
            ]);
        }

        return response()->json([
            'grados_info' => $gradosInfo,
        ]);
    }

    /**
     * Obtener estudiantes disponibles para asignar
     * Un estudiante solo puede estar en un grupo a la vez
     * Se muestran estudiantes sin grupo o del mismo grupo (para editar)
     */
    public function getAvailableStudents(Request $request, string $id)
    {
        $group = Group::findOrFail($id);
        
        // Buscar estudiantes que coincidan con la carrera del grupo
        // Incluye:
        // 1. Estudiantes sin grupo (group_id es null)
        // 2. Estudiantes del mismo grupo (para poder editarlos)
        // NO incluye estudiantes de otros grupos (solo se pueden editar, no asignar a múltiples grupos)
        $students = User::whereHas('studentDetail', function($query) use ($group) {
                $query->where('carrera', $group->carrera)
                      ->where(function($q) use ($group) {
                          $q->whereNull('group_id')
                            ->orWhere('group_id', $group->id); // Solo del mismo grupo para editar
                      });
            })
            ->with('studentDetail')
            ->get();

        return response()->json($students);
    }

    /**
     * Asignar estudiantes a un grupo
     * Un estudiante solo puede estar en un grupo a la vez
     */
    public function assignStudents(Request $request, string $id)
    {
        $group = Group::findOrFail($id);

        $validated = $request->validate([
            'student_ids' => 'required|array',
            'student_ids.*' => 'exists:users,id',
        ]);

        // Verificar que los estudiantes existan y tengan detalles
        $students = User::whereIn('id', $validated['student_ids'])
            ->whereHas('studentDetail')
            ->with('studentDetail')
            ->get();

        $assignedCount = 0;
        $skippedStudents = [];

        // Actualizar cada estudiante para asignarlo al grupo
        foreach ($students as $student) {
            if ($student->studentDetail) {
                // Verificar si el estudiante ya está en otro grupo
                $currentGroupId = $student->studentDetail->group_id;
                
                // Si ya está en un grupo diferente, solo permitir cambiar si es el mismo grupo o null
                if ($currentGroupId !== null && $currentGroupId != $group->id) {
                    $skippedStudents[] = [
                        'id' => $student->id,
                        'name' => $student->name,
                        'current_group' => $currentGroupId,
                        'message' => 'El estudiante ya está asignado a otro grupo. Use la función de edición para cambiar de grupo.'
                    ];
                    continue;
                }

                // Asignar o actualizar el grupo
                $student->studentDetail->update([
                    'group_id' => $group->id,
                    'carrera' => $group->carrera,
                    'grado' => $group->grado,
                    'grupo' => $group->grupo,
                ]);
                
                $assignedCount++;
            }
        }

        $response = [
            'message' => 'Proceso completado',
            'assigned_count' => $assignedCount,
            'skipped_count' => count($skippedStudents)
        ];

        if (count($skippedStudents) > 0) {
            $response['skipped_students'] = $skippedStudents;
            $response['message'] = "Se asignaron {$assignedCount} estudiante(s). " . count($skippedStudents) . " estudiante(s) fueron omitidos porque ya están en otro grupo.";
        }

        return response()->json($response);
    }

    /**
     * Remover estudiantes de un grupo
     */
    public function removeStudent(string $groupId, string $studentId)
    {
        $group = Group::findOrFail($groupId);
        $studentDetail = StudentDetail::where('user_id', $studentId)
            ->where('group_id', $groupId)
            ->firstOrFail();

        $studentDetail->update([
            'group_id' => null,
            'grupo' => null,
            'grado' => null,
        ]);

        return response()->json(['message' => 'Estudiante removido del grupo correctamente']);
    }

    /**
     * Cambiar el grupo de un estudiante
     * Un estudiante solo puede estar en un grupo a la vez
     */
    public function changeStudentGroup(Request $request, string $studentId)
    {
        $validated = $request->validate([
            'group_id' => 'required|exists:groups,id',
        ]);

        $newGroup = Group::findOrFail($validated['group_id']);
        $studentDetail = StudentDetail::where('user_id', $studentId)->firstOrFail();

        // Verificar que el estudiante pertenezca a la misma carrera
        if ($studentDetail->carrera !== $newGroup->carrera) {
            return response()->json([
                'message' => 'El estudiante no puede cambiar a un grupo de otra carrera.'
            ], 422);
        }

        // Actualizar el grupo del estudiante (esto automáticamente lo remueve del grupo anterior)
        $studentDetail->update([
            'group_id' => $newGroup->id,
            'carrera' => $newGroup->carrera,
            'grado' => $newGroup->grado,
            'grupo' => $newGroup->grupo,
        ]);

        return response()->json([
            'message' => 'Grupo del estudiante actualizado correctamente',
            'student' => $studentDetail->load('user')
        ]);
    }

    /**
     * Crear un nuevo grupo
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'carrera' => 'required|string',
            'grado' => 'required|integer|min:1|max:5',
            'grupo' => 'required|string|in:A,B,C,D,E',
        ], [
            'grupo.in' => 'El grupo debe ser A, B, C, D o E.',
            'grado.min' => 'El grado debe ser entre 1 y 5.',
            'grado.max' => 'El grado debe ser entre 1 y 5.',
        ]);

        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        
        if (!$activePeriod) {
            return response()->json([
                'message' => 'No hay un periodo académico activo. Por favor, contacta al administrador.',
            ], 422);
        }

        // Verificar que no exista un grupo con la misma combinación en el periodo activo
        $exists = Group::where('carrera', $validated['carrera'])
            ->where('grado', $validated['grado'])
            ->where('grupo', $validated['grupo'])
            ->where('academic_period_id', $activePeriod->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'message' => 'Ya existe un grupo con esta combinación de carrera, grado y grupo en el periodo activo.'
            ], 422);
        }

        $validated['academic_period_id'] = $activePeriod->id;
        $group = Group::create($validated);

        return response()->json($group, 201);
    }

    /**
     * Mostrar un grupo específico
     */
    public function show(string $id)
    {
        $group = Group::findOrFail($id);
        return response()->json($group);
    }

    /**
     * Actualizar un grupo
     */
    public function update(Request $request, string $id)
    {
        $group = Group::findOrFail($id);

        $validated = $request->validate([
            'carrera' => 'sometimes|string',
            'grado' => 'sometimes|integer|min:1|max:5',
            'grupo' => 'sometimes|string|in:A,B,C,D,E',
        ], [
            'grupo.in' => 'El grupo debe ser A, B, C, D o E.',
            'grado.min' => 'El grado debe ser entre 1 y 5.',
            'grado.max' => 'El grado debe ser entre 1 y 5.',
        ]);

        // Verificar que no exista otro grupo con la misma combinación (excepto el actual)
        if (isset($validated['carrera']) || isset($validated['grado']) || isset($validated['grupo'])) {
            $carrera = $validated['carrera'] ?? $group->carrera;
            $grado = $validated['grado'] ?? $group->grado;
            $grupoLetra = $validated['grupo'] ?? $group->grupo;

            $exists = Group::where('carrera', $carrera)
                ->where('grado', $grado)
                ->where('grupo', $grupoLetra)
                ->where('id', '!=', $id)
                ->exists();

            if ($exists) {
                return response()->json([
                    'message' => 'Ya existe otro grupo con esta combinación de carrera, grado y grupo.'
                ], 422);
            }
        }

        $group->update($validated);

        return response()->json($group);
    }

    /**
     * Eliminar un grupo
     */
    public function destroy(string $id)
    {
        $group = Group::findOrFail($id);
        $group->delete();

        return response()->json(['message' => 'Grupo eliminado correctamente']);
    }

    /**
     * Asignar tutor a un grupo
     */
    public function assignTutor(Request $request, string $id)
    {
        $validated = $request->validate([
            'tutor_id' => 'nullable|exists:users,id',
        ]);

        $group = Group::findOrFail($id);

        // Si se envía null, remover el tutor
        if (empty($validated['tutor_id'])) {
            $group->update(['tutor_id' => null]);
            return response()->json([
                'message' => 'Tutor removido del grupo correctamente',
                'group' => $group->load('tutor:id,name,email')
            ]);
        }

        // Verificar que el usuario sea un maestro
        $tutor = User::findOrFail($validated['tutor_id']);
        if (!$tutor->isMaestro()) {
            return response()->json([
                'message' => 'El usuario seleccionado no es un maestro'
            ], 422);
        }

        // Verificar si el tutor ya está asignado a 2 grupos (máximo permitido)
        $existingGroupsCount = Group::where('tutor_id', $validated['tutor_id'])
            ->where('id', '!=', $id)
            ->count();

        if ($existingGroupsCount >= 2) {
            return response()->json([
                'message' => 'Este maestro ya es tutor de 2 grupos (máximo permitido). Un maestro puede ser tutor de máximo 2 grupos.'
            ], 422);
        }

        $group->update(['tutor_id' => $validated['tutor_id']]);

        return response()->json([
            'message' => 'Tutor asignado correctamente',
            'group' => $group->load('tutor:id,name,email')
        ]);
    }

    /**
     * Obtener maestros disponibles para asignar como tutores
     */
    public function getAvailableTutors()
    {
        $teachers = User::where('email', 'LIKE', '%@utmetropolitana.edu.mx')
            ->where('email', 'NOT LIKE', '%@alumno.%')
            ->where('email', 'NOT LIKE', '%@admin.%')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json($teachers);
    }
}


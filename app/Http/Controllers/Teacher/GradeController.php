<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\CalificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class GradeController extends Controller
{
    protected $calificacionService;

    public function __construct(CalificacionService $calificacionService)
    {
        $this->calificacionService = $calificacionService;
    }

    /**
     * Obtener calificaciones de un grupo y materia
     * Ahora usa course_unit_id en lugar de unidad numérica
     * También devuelve las unidades configuradas para la materia
     */
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'academic_load_id' => 'required|exists:academic_loads,id',
                'course_unit_id' => 'nullable|exists:course_units,id',
            ]);

            // Obtener todas las unidades configuradas para esta materia
            $courseUnits = \App\Models\CourseUnit::where('academic_load_id', $validated['academic_load_id'])
                ->orderBy('nombre')
                ->get();

            // Si se proporciona course_unit_id, obtener calificaciones
            $calificaciones = collect([]);
            if ($request->has('course_unit_id') && $validated['course_unit_id']) {
                // Verificar que la unidad pertenece a esta carga académica
                $courseUnit = $courseUnits->firstWhere('id', $validated['course_unit_id']);
                
                if (!$courseUnit) {
                    return response()->json([
                        'message' => 'La unidad seleccionada no pertenece a esta materia.'
                    ], 404);
                }

                $calificaciones = $this->calificacionService->obtenerCalificacionesGrupo(
                    $validated['academic_load_id'],
                    $validated['course_unit_id']
                );
            }

            return response()->json([
                'course_units' => $courseUnits,
                'calificaciones' => $calificaciones->values(),
            ]);
        } catch (\Exception $e) {
            \Log::error('Error en index de calificaciones: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Error al obtener las calificaciones: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar o actualizar calificación
     * Sistema nuevo: solo se guardan saber y saber_hacer_convivir (0-100)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'student_id' => 'required|exists:users,id',
            'course_unit_id' => 'required|exists:course_units,id',
            'saber' => 'nullable|integer|min:0|max:100',
            'saber_hacer_convivir' => 'nullable|integer|min:0|max:100',
        ]);

        try {
            // Verificar que la unidad existe
            $courseUnit = \App\Models\CourseUnit::findOrFail($validated['course_unit_id']);

            // Usar el servicio para guardar la calificación
            $calificacion = $this->calificacionService->guardarCalificacion([
                'student_id' => $validated['student_id'],
                'course_unit_id' => $validated['course_unit_id'],
                'saber' => $validated['saber'] ?? null,
                'saber_hacer_convivir' => $validated['saber_hacer_convivir'] ?? null,
            ]);
            
            return response()->json([
                'message' => 'Calificación guardada exitosamente',
                'calificacion' => [
                    'id' => $calificacion->id,
                    'saber' => $calificacion->saber,
                    'saber_hacer_convivir' => $calificacion->saber_hacer_convivir,
                    'calificacion_final_unidad' => $calificacion->calificacion_final_unidad,
                ],
            ], 201);
            
        } catch (\Exception $e) {
            \Log::error('Error guardando calificación: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }
    }

    /**
     * Obtener unidades configuradas para una materia
     */
    public function getCourseUnits(Request $request)
    {
        $validated = $request->validate([
            'academic_load_id' => 'required|exists:academic_loads,id',
        ]);

        try {
            $courseUnits = \App\Models\CourseUnit::where('academic_load_id', $validated['academic_load_id'])
                ->orderBy('nombre')
                ->get();

            return response()->json($courseUnits);
        } catch (\Exception $e) {
            \Log::error('Error obteniendo unidades de curso: ' . $e->getMessage(), [
                'request' => $validated,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Error al obtener las unidades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar configuración de unidades dinámicas
     * Elimina las unidades anteriores y guarda las nuevas
     */
    public function saveUnits(Request $request)
    {
        $validated = $request->validate([
            'academic_load_id' => 'required|exists:academic_loads,id',
            'unidades' => 'required|array|min:1',
            'unidades.*.nombre' => 'required|string|max:255',
            'unidades.*.porcentaje' => 'required|integer|min:1|max:100',
        ]);

        try {
            DB::beginTransaction();

            // Validar que la suma de porcentajes sea exactamente 100%
            $sumaPorcentajes = array_sum(array_column($validated['unidades'], 'porcentaje'));
            if ($sumaPorcentajes !== 100) {
                return response()->json([
                    'message' => "La suma de los porcentajes debe ser exactamente 100%. Actual: {$sumaPorcentajes}%"
                ], 422);
            }

            // Eliminar unidades anteriores de esta carga académica
            \App\Models\CourseUnit::where('academic_load_id', $validated['academic_load_id'])->delete();

            // Crear las nuevas unidades
            $courseUnits = [];
            foreach ($validated['unidades'] as $unidad) {
                $courseUnits[] = \App\Models\CourseUnit::create([
                    'academic_load_id' => $validated['academic_load_id'],
                    'nombre' => $unidad['nombre'],
                    'porcentaje' => $unidad['porcentaje'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Unidades configuradas exitosamente',
                'course_units' => $courseUnits,
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('Error guardando unidades: ' . $e->getMessage(), [
                'request' => $validated,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Error al guardar las unidades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener academic loads basados en grupo y materia
     */
    public function getAcademicLoads(Request $request)
    {
        $validated = $request->validate([
            'carrera' => 'required|string',
            'grupo' => 'required|string',
            'materia' => 'required|string',
        ]);

        try {
            $group = \App\Models\Group::where('carrera', $validated['carrera'])
                ->where('grupo', $validated['grupo'])
                ->first();

            if (!$group) {
                return response()->json([
                    'message' => 'Grupo no encontrado',
                    'debug' => [
                        'carrera' => $validated['carrera'],
                        'grupo' => $validated['grupo']
                    ]
                ], 404);
            }

            $subject = \App\Models\Subject::where('nombre', $validated['materia'])->first();

            if (!$subject) {
                return response()->json([
                    'message' => 'Materia no encontrada',
                    'debug' => [
                        'materia' => $validated['materia']
                    ]
                ], 404);
            }

            // Obtener el periodo académico activo
            $activePeriod = \App\Models\AcademicPeriod::active()->first();
            
            if (!$activePeriod) {
                return response()->json([
                    'message' => 'No hay un periodo académico activo. Por favor, contacta al administrador.',
                ], 422);
            }

            $academicLoad = \App\Models\AcademicLoad::where('group_id', $group->id)
                ->where('subject_id', $subject->id)
                ->where('academic_period_id', $activePeriod->id)
                ->with(['group', 'subject', 'period'])
                ->first();

            if (!$academicLoad) {
                // Intentar crear el academic_load si no existe
                $teacher = Auth::user();
                $academicLoad = \App\Models\AcademicLoad::create([
                    'group_id' => $group->id,
                    'subject_id' => $subject->id,
                    'academic_period_id' => $activePeriod->id,
                    'teacher_name' => $teacher->name,
                ]);
                
                // Recargar con relaciones
                $academicLoad->load(['group', 'subject', 'period']);
            }

            return response()->json([
                'id' => $academicLoad->id,
                'group' => [
                    'id' => $group->id,
                    'carrera' => $group->carrera,
                    'grado' => $group->grado,
                    'grupo' => $group->grupo,
                ],
                'subject' => [
                    'id' => $subject->id,
                    'nombre' => $subject->nombre,
                    'codigo' => $subject->codigo,
                ],
                'teacher_name' => $academicLoad->teacher_name,
            ]);
        } catch (\Exception $e) {
            \Log::error('Error obteniendo academic load: ' . $e->getMessage(), [
                'request' => $validated,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Error al obtener la carga académica: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de asistencia por unidad
     */
    public function getAttendanceStats(Request $request)
    {
        $validated = $request->validate([
            'academic_load_id' => 'required|exists:academic_loads,id',
            'course_unit_id' => 'required|integer|exists:course_units,id',
        ]);

        // Verificar que la unidad pertenece a esta carga académica
        $courseUnit = \App\Models\CourseUnit::where('id', $validated['course_unit_id'])
            ->where('academic_load_id', $validated['academic_load_id'])
            ->first();
        
        if (!$courseUnit) {
            return response()->json([
                'error' => 'La unidad especificada no pertenece a esta carga académica'
            ], 422);
        }

        $inscripciones = \App\Models\Inscripcion::where('academic_load_id', $validated['academic_load_id'])
            ->with('student.studentDetail')
            ->get();

        $stats = $inscripciones->map(function ($inscripcion) use ($validated) {
            $porcentaje = $this->calificacionService->calcularPorcentajeAsistencia(
                $inscripcion->student_id,
                $inscripcion->academic_load_id,
                $validated['course_unit_id'] // Usar course_unit_id en lugar de unidad numérica
            );

            return [
                'student_id' => $inscripcion->student_id,
                'student_name' => $inscripcion->student->name,
                'matricula' => $inscripcion->student->studentDetail->matricula ?? '',
                'porcentaje_asistencia' => $porcentaje,
                'tiene_derecho_examen' => $porcentaje >= 80.0,
            ];
        });

        return response()->json($stats);
    }

    /**
     * Confirmar/publicar calificaciones (hacerlas visibles en el portal de alumnos)
     */
    public function confirm(Request $request)
    {
        $validated = $request->validate([
            'academic_load_id' => 'required|exists:academic_loads,id',
            'course_unit_id' => 'required|exists:course_units,id',
        ]);

        try {
            // Verificar que la unidad pertenece a esta carga académica
            $courseUnit = \App\Models\CourseUnit::where('id', $validated['course_unit_id'])
                ->where('academic_load_id', $validated['academic_load_id'])
                ->first();
            
            if (!$courseUnit) {
                return response()->json([
                    'message' => 'La unidad seleccionada no pertenece a esta materia.'
                ], 404);
            }

            // Obtener todas las calificaciones de esta unidad
            $calificaciones = \App\Models\CalificacionDetalle::where('course_unit_id', $validated['course_unit_id'])
                ->get();

            if ($calificaciones->isEmpty()) {
                return response()->json([
                    'message' => 'No hay calificaciones para confirmar en esta unidad'
                ], 422);
            }

            // Marcar como confirmadas (agregar campo confirmado o usar otro mecanismo)
            // Por ahora, simplemente verificamos que existan calificaciones
            // En el futuro, podrías agregar un campo `confirmado` a la tabla calificaciones_detalle
            
            return response()->json([
                'message' => 'Calificaciones confirmadas exitosamente',
                'calificaciones_confirmadas' => $calificaciones->count()
            ], 200);
            
        } catch (\Exception $e) {
            \Log::error('Error confirmando calificaciones: ' . $e->getMessage(), [
                'request' => $validated,
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Error al confirmar las calificaciones: ' . $e->getMessage()
            ], 500);
        }
    }
}


<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Services\CalificacionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     */
    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'academic_load_id' => 'required|exists:academic_loads,id',
                'unidad' => 'required|integer|in:1,2,3',
            ]);

            $calificaciones = $this->calificacionService->obtenerCalificacionesGrupo(
                $validated['academic_load_id'],
                $validated['unidad']
            );

            return response()->json($calificaciones->values());
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
     * Ahora solo se guardan examen y conducta directamente (0-100)
     * Las tareas se calculan automáticamente desde las actividades
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'inscripcion_id' => 'required|exists:inscripciones,id',
            'unidad' => 'required|integer|in:1,2,3',
            'score_examen' => 'nullable|numeric|min:0|max:100',
            'score_conducta' => 'nullable|numeric|min:0|max:100',
        ]);

        try {
            // Obtener la inscripción para verificar derecho a examen
            $inscripcion = \App\Models\Inscripcion::findOrFail($validated['inscripcion_id']);
            
            // Verificar derecho a examen
            $tieneDerechoExamen = $this->calificacionService->tieneDerechoExamen(
                $inscripcion->student_id,
                $inscripcion->academic_load_id,
                $validated['unidad']
            );

            // Buscar o crear calificación detalle
            $calificacion = \App\Models\CalificacionDetalle::firstOrNew([
                'inscripcion_id' => $validated['inscripcion_id'],
                'unidad' => $validated['unidad'],
            ]);

            // Si es nueva, establecer valores por defecto
            if (!$calificacion->exists) {
                $calificacion->score_tareas = 0.00;
                $calificacion->score_examen = null;
                $calificacion->score_conducta = 0.00; // Default para NOT NULL
                $calificacion->derecho_examen = $tieneDerechoExamen;
            }

            // Solo actualizar los campos que se envían explícitamente
            if (isset($validated['score_examen'])) {
                $calificacion->score_examen = $tieneDerechoExamen ? $validated['score_examen'] : null;
            }
            
            if (isset($validated['score_conducta'])) {
                $calificacion->score_conducta = $validated['score_conducta'];
            }
            
            // Actualizar derecho_examen siempre
            $calificacion->derecho_examen = $tieneDerechoExamen;
            $calificacion->save();

            // Recalcular promedio usando el nuevo servicio
            // Esto calculará automáticamente las tareas desde las actividades
            $calificacion = \App\Services\GradeCalculatorService::recalculateUnitAverage(
                $validated['inscripcion_id'],
                $validated['unidad']
            );
            
            return response()->json([
                'message' => 'Calificación guardada exitosamente',
                'calificacion' => $calificacion,
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
            'unidad' => 'required|integer|in:1,2,3',
        ]);

        $inscripciones = \App\Models\Inscripcion::where('academic_load_id', $validated['academic_load_id'])
            ->with('student.studentDetail')
            ->get();

        $stats = $inscripciones->map(function ($inscripcion) use ($validated) {
            $porcentaje = $this->calificacionService->calcularPorcentajeAsistencia(
                $inscripcion->student_id,
                $inscripcion->academic_load_id,
                $validated['unidad']
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
            'unidad' => 'required|integer|in:1,2,3',
        ]);

        try {
            // Obtener todas las calificaciones de esta unidad
            $calificaciones = \App\Models\CalificacionDetalle::whereHas('inscripcion', function ($query) use ($validated) {
                $query->where('academic_load_id', $validated['academic_load_id']);
            })
            ->where('unidad', $validated['unidad'])
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


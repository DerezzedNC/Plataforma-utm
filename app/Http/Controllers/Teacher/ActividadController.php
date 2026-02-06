<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Actividad;
use App\Models\ActividadEntrega;
use App\Services\CalculadoraDePromedios;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class ActividadController extends Controller
{
    protected $calculadora;

    public function __construct(CalculadoraDePromedios $calculadora)
    {
        $this->calculadora = $calculadora;
    }

    /**
     * Listar actividades de un academic_load y unidad
     */
    public function index(Request $request)
    {
        try {
            // Verificar si la tabla existe
            if (!Schema::hasTable('actividades')) {
                return response()->json([]);
            }

            $validated = $request->validate([
                'academic_load_id' => 'required|exists:academic_loads,id',
                'unidad' => 'required|integer|in:1,2,3',
            ]);

            $actividades = Actividad::where('academic_load_id', $validated['academic_load_id'])
                ->where('unidad', $validated['unidad'])
                ->orderBy('categoria')
                ->orderBy('created_at')
                ->get();

            return response()->json($actividades);
        } catch (\Exception $e) {
            \Log::error('Error listando actividades: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            // Si la tabla no existe, devolver array vacío
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), "Base table or view not found")) {
                return response()->json([]);
            }
            
            return response()->json([
                'message' => 'Error al obtener las actividades: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Crear una nueva actividad
     */
    public function store(Request $request)
    {
        try {
            // Verificar si la tabla existe
            if (!Schema::hasTable('actividades')) {
                return response()->json([
                    'message' => 'Las tablas de actividades no están disponibles. Por favor ejecuta las migraciones primero.'
                ], 500);
            }

            $validated = $request->validate([
                'academic_load_id' => 'required|exists:academic_loads,id',
                'titulo' => 'required|string|max:255',
                'descripcion' => 'nullable|string',
                'unidad' => 'required|integer|in:1,2,3',
                'valor_maximo' => 'required|numeric|min:0.01|max:100',
                'categoria' => 'required|in:TAREA,EXAMEN,CONDUCTA',
                'fecha_limite' => 'nullable|date',
            ]);

            // Asegurar que activa tenga un valor por defecto
            if (!isset($validated['activa'])) {
                $validated['activa'] = true;
            }

            $actividad = Actividad::create($validated);

            return response()->json([
                'message' => 'Actividad creada exitosamente',
                'actividad' => $actividad
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::error('Error de base de datos creando actividad: ' . $e->getMessage(), [
                'request' => $request->all(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
            ]);
            
            // Verificar si es un error de tabla no encontrada
            if (str_contains($e->getMessage(), "doesn't exist") || 
                str_contains($e->getMessage(), "Base table or view not found") ||
                str_contains($e->getMessage(), "Unknown table")) {
                return response()->json([
                    'message' => 'Las tablas de actividades no están disponibles. Por favor ejecuta las migraciones: php artisan migrate'
                ], 500);
            }
            
            return response()->json([
                'message' => 'Error de base de datos al crear la actividad: ' . $e->getMessage()
            ], 500);
        } catch (\Exception $e) {
            Log::error('Error creando actividad: ' . $e->getMessage(), [
                'request' => $request->all(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'message' => 'Error al crear la actividad: ' . $e->getMessage(),
                'type' => get_class($e)
            ], 500);
        }
    }

    /**
     * Actualizar una actividad
     */
    public function update(Request $request, $id)
    {
        $actividad = Actividad::findOrFail($id);

        $validated = $request->validate([
            'titulo' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'valor_maximo' => 'sometimes|required|numeric|min:0.01|max:100',
            'categoria' => 'sometimes|required|in:TAREA,EXAMEN,CONDUCTA',
            'fecha_limite' => 'nullable|date',
            'activa' => 'sometimes|boolean',
        ]);

        try {
            $actividad->update($validated);

            // Si se cambió valor_maximo o categoria, recalcular todas las calificaciones afectadas
            if (isset($validated['valor_maximo']) || isset($validated['categoria'])) {
                $this->recalcularCalificacionesActividad($actividad);
            }

            return response()->json([
                'message' => 'Actividad actualizada exitosamente',
                'actividad' => $actividad->fresh()
            ]);
        } catch (\Exception $e) {
            Log::error('Error actualizando actividad: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al actualizar la actividad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar una actividad
     */
    public function destroy($id)
    {
        try {
            $actividad = Actividad::findOrFail($id);
            
            // Guardar datos antes de eliminar para recalcular
            $academicLoadId = $actividad->academic_load_id;
            $unidad = $actividad->unidad;
            $categoria = $actividad->categoria;
            
            // Obtener estudiantes afectados
            $estudiantesIds = ActividadEntrega::where('actividad_id', $id)
                ->pluck('student_id')
                ->unique();

            // Eliminar entregas
            ActividadEntrega::where('actividad_id', $id)->delete();
            
            // Eliminar actividad
            $actividad->delete();

            // Recalcular calificaciones de estudiantes afectados
            foreach ($estudiantesIds as $studentId) {
                $this->calculadora->actualizarCalificacionDesdeActividades(
                    $studentId,
                    $academicLoadId,
                    $unidad
                );
            }

            return response()->json([
                'message' => 'Actividad eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            Log::error('Error eliminando actividad: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al eliminar la actividad: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Guardar o actualizar calificación de una entrega
     */
    public function calificarEntrega(Request $request)
    {
        $validated = $request->validate([
            'actividad_id' => 'required|exists:actividades,id',
            'student_id' => 'required|exists:users,id',
            'calificacion_obtenida' => 'required|numeric|min:0',
            'observaciones' => 'nullable|string',
        ]);

        try {
            $actividad = Actividad::findOrFail($validated['actividad_id']);

            // Convertir a float para comparación correcta
            $calificacion = (float) $validated['calificacion_obtenida'];
            $valorMaximo = (float) $actividad->valor_maximo;

            // Log para debugging
            Log::info('Validando calificación', [
                'actividad_id' => $actividad->id,
                'calificacion' => $calificacion,
                'valor_maximo' => $valorMaximo,
                'tipo_calificacion' => gettype($calificacion),
                'tipo_valor_maximo' => gettype($valorMaximo),
            ]);

            // Validar que no sea negativa
            if ($calificacion < 0) {
                return response()->json([
                    'message' => 'La calificación no puede ser negativa'
                ], 422);
            }

            // Validar que no supere el valor máximo (con un pequeño margen para decimales)
            if ($calificacion > $valorMaximo + 0.01) {
                return response()->json([
                    'message' => "La calificación ({$calificacion}) no puede superar el valor máximo de {$valorMaximo} puntos"
                ], 422);
            }

            $entrega = ActividadEntrega::updateOrCreate(
                [
                    'actividad_id' => $validated['actividad_id'],
                    'student_id' => $validated['student_id'],
                ],
                [
                    'calificacion_obtenida' => $validated['calificacion_obtenida'],
                    'observaciones' => $validated['observaciones'] ?? null,
                    'calificado_en' => now(),
                    'calificado_por' => Auth::id(),
                ]
            );

            // Actualizar calificación automáticamente (se hace en el boot del modelo)
            // Pero lo hacemos explícitamente aquí también para asegurar
            $this->calculadora->actualizarCalificacionDesdeActividades(
                $validated['student_id'],
                $actividad->academic_load_id,
                $actividad->unidad
            );

            return response()->json([
                'message' => 'Calificación guardada exitosamente',
                'entrega' => $entrega->fresh()
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error calificando entrega: ' . $e->getMessage());
            return response()->json([
                'message' => 'Error al guardar la calificación: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener entregas de una actividad
     */
    public function getEntregas(Request $request, $actividadId)
    {
        $actividad = Actividad::findOrFail($actividadId);

        $entregas = ActividadEntrega::where('actividad_id', $actividadId)
            ->with('student.studentDetail')
            ->get();

        return response()->json($entregas);
    }

    /**
     * Recalcular calificaciones de todos los estudiantes afectados por una actividad
     */
    private function recalcularCalificacionesActividad(Actividad $actividad)
    {
        $entregas = ActividadEntrega::where('actividad_id', $actividad->id)
            ->pluck('student_id')
            ->unique();

        foreach ($entregas as $studentId) {
            $this->calculadora->actualizarCalificacionDesdeActividades(
                $studentId,
                $actividad->academic_load_id,
                $actividad->unidad
            );
        }
    }
}


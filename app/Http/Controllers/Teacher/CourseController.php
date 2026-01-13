<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Career;
use App\Models\Area;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CourseController extends Controller
{
    /**
     * Obtener todos los cursos del maestro autenticado
     */
    public function index()
    {
        $teacher = Auth::user();
        
        $courses = Course::where('teacher_id', $teacher->id)
            ->with(['careers.area'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($courses);
    }

    /**
     * Obtener todas las carreras organizadas por áreas
     */
    public function getCareers()
    {
        $areas = Area::with('careers')->get();
        
        return response()->json($areas);
    }

    /**
     * Crear un nuevo curso
     */
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'tipo' => 'required|in:interno,externo',
                'nombre' => 'required|string|max:255',
                'descripcion' => 'required|string',
                'tiempo_duracion' => 'required|string|max:255',
                'costo' => 'nullable|numeric|min:0',
                'link' => 'nullable|url|max:500',
                'aula' => 'nullable|string|max:255',
                'career_ids' => 'required|array|min:1',
                'career_ids.*' => 'required|integer|exists:careers,id',
            ]);

            $teacher = Auth::user();

            $course = Course::create([
                'teacher_id' => $teacher->id,
                'tipo' => $validated['tipo'],
                'nombre' => $validated['nombre'],
                'descripcion' => $validated['descripcion'],
                'tiempo_duracion' => $validated['tiempo_duracion'],
                'costo' => $validated['costo'] ?? null,
                'link' => $validated['link'] ?? null,
                'aula' => $validated['aula'] ?? null,
                'activo' => true,
            ]);

            // Asociar carreras al curso
            $course->careers()->sync($validated['career_ids']);

            return response()->json($course->load(['careers.area', 'teacher']), 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error creando curso: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'error' => 'Error al crear el curso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mostrar un curso específico
     */
    public function show(string $course)
    {
        $teacher = Auth::user();
        
        $course = Course::where('id', $course)
            ->where('teacher_id', $teacher->id)
            ->with(['careers.area', 'teacher'])
            ->firstOrFail();

        return response()->json($course);
    }

    /**
     * Actualizar un curso
     */
    public function update(Request $request, string $course)
    {
        try {
            $teacher = Auth::user();
            
            $course = Course::where('id', $course)
                ->where('teacher_id', $teacher->id)
                ->firstOrFail();

            $validated = $request->validate([
                'tipo' => 'sometimes|in:interno,externo',
                'nombre' => 'sometimes|string|max:255',
                'descripcion' => 'sometimes|string',
                'tiempo_duracion' => 'sometimes|string|max:255',
                'costo' => 'nullable|numeric|min:0',
                'link' => 'nullable|url|max:500',
                'aula' => 'nullable|string|max:255',
                'activo' => 'sometimes|boolean',
                'career_ids' => 'sometimes|array|min:1',
                'career_ids.*' => 'sometimes|integer|exists:careers,id',
            ]);

            $course->update($validated);

            // Actualizar carreras asociadas si se proporcionan
            if (isset($validated['career_ids'])) {
                $course->careers()->sync($validated['career_ids']);
            }

            return response()->json($course->load(['careers.area', 'teacher']));
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación: ' . $e->getMessage(),
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error actualizando curso: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'request' => $request->all(),
            ]);
            
            return response()->json([
                'error' => 'Error al actualizar el curso: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Eliminar un curso
     */
    public function destroy(string $course)
    {
        try {
            $teacher = Auth::user();
            
            $course = Course::where('id', $course)
                ->where('teacher_id', $teacher->id)
                ->firstOrFail();

            $course->delete();

            return response()->json(['message' => 'Curso eliminado exitosamente']);
        } catch (\Exception $e) {
            Log::error('Error eliminando curso: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Error al eliminar el curso: ' . $e->getMessage()
            ], 500);
        }
    }
}


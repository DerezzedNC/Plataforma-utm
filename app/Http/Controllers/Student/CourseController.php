<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * Obtener cursos disponibles para el estudiante autenticado
     */
    public function index(Request $request)
    {
        try {
            $student = Auth::user();
            
            // Cargar studentDetail con la relación group
            $studentDetail = $student->studentDetail()->with('group')->first();

            // Buscar cursos activos
            $query = Course::where('activo', true)
                ->with(['teacher', 'careers.area']);

            // Si el estudiante tiene detalles, intentar filtrar por carrera
            if ($studentDetail) {
                // Obtener la carrera del estudiante
                $career = $studentDetail->carrera;
                
                // Si tiene grupo asignado, usar la carrera del grupo
                if ($studentDetail->group) {
                    $career = $studentDetail->group->carrera;
                }

                \Log::debug('Student CourseController - Carrera del estudiante:', [
                    'student_id' => $student->id,
                    'carrera_studentDetail' => $studentDetail->carrera,
                    'carrera_group' => $studentDetail->group ? $studentDetail->group->carrera : null,
                    'carrera_final' => $career,
                ]);

                // Si el estudiante tiene carrera, filtrar por carreras relacionadas
                if ($career) {
                    // Buscar la carrera por nombre (búsqueda exacta)
                    $careerModel = \App\Models\Career::where('nombre', $career)->first();
                    
                    if ($careerModel) {
                        \Log::debug('Student CourseController - Carrera encontrada en careers:', [
                            'career_id' => $careerModel->id,
                            'career_nombre' => $careerModel->nombre,
                        ]);
                        
                        // Obtener cursos que incluyan esta carrera
                        $query->whereHas('careers', function($q) use ($careerModel) {
                            $q->where('careers.id', $careerModel->id);
                        });
                    } else {
                        // Si no encuentra la carrera, intentar búsqueda parcial (case insensitive)
                        $careerModel = \App\Models\Career::whereRaw('LOWER(nombre) = LOWER(?)', [$career])->first();
                        
                        if ($careerModel) {
                            \Log::debug('Student CourseController - Carrera encontrada (case insensitive):', [
                                'career_id' => $careerModel->id,
                                'career_nombre' => $careerModel->nombre,
                            ]);
                            
                            $query->whereHas('careers', function($q) use ($careerModel) {
                                $q->where('careers.id', $careerModel->id);
                            });
                        } else {
                            \Log::warning('Student CourseController - No se encontró la carrera en la tabla careers:', [
                                'carrera_buscada' => $career,
                            ]);
                            // Si no encuentra la carrera, mostrar todos los cursos activos (sin filtrar)
                        }
                    }
                }
            }
            // Si no tiene detalles, mostrar todos los cursos activos

            // Filtro por tipo si se proporciona
            if ($request->has('tipo')) {
                $query->where('tipo', $request->tipo);
            }

            $courses = $query->orderBy('created_at', 'desc')->get();

            \Log::debug('Student CourseController - Cursos encontrados:', [
                'total_courses' => $courses->count(),
                'courses' => $courses->map(function($course) {
                    return [
                        'id' => $course->id,
                        'nombre' => $course->nombre,
                        'careers' => $course->careers->pluck('nombre')->toArray(),
                    ];
                })->toArray(),
            ]);

            return response()->json($courses);
        } catch (\Exception $e) {
            \Log::error('Error en Student/CourseController@index: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'student_id' => Auth::id(),
            ]);
            
            // En caso de error, retornar array vacío en lugar de error 500
            return response()->json([]);
        }
    }

    /**
     * Obtener un curso específico
     */
    public function show(string $course)
    {
        $course = Course::where('id', $course)
            ->where('activo', true)
            ->with(['teacher', 'careers.area'])
            ->firstOrFail();

        return response()->json($course);
    }
}


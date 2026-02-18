<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    /**
     * Obtener el perfil completo del estudiante autenticado
     */
    public function show()
    {
        $user = Auth::user();
        $studentDetail = $user->studentDetail;
        
        if (!$studentDetail) {
            return response()->json([
                'message' => 'No se encontraron detalles del estudiante'
            ], 404);
        }

        // Cargar información del grupo si existe
        $group = $studentDetail->group;
        
        return response()->json([
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
            'student_detail' => [
                'id' => $studentDetail->id,
                'matricula' => $studentDetail->matricula,
                'carrera' => $studentDetail->carrera,
                'grado' => $studentDetail->grado,
                'grupo' => $studentDetail->grupo,
                'telefono' => $studentDetail->telefono,
                'direccion' => $studentDetail->direccion,
                'fecha_nacimiento' => $studentDetail->fecha_nacimiento,
                'foto_perfil' => $studentDetail->foto_perfil,
                'promedio_general' => $studentDetail->promedio_general,
                'creditos_totales' => $studentDetail->creditos_totales,
                'group' => $group ? [
                    'id' => $group->id,
                    'carrera' => $group->carrera,
                    'grado' => $group->grado,
                    'grupo' => $group->grupo,
                ] : null,
            ]
        ]);
    }

    /**
     * Actualizar información personal del estudiante
     */
    public function updatePersonalInfo(Request $request)
    {
        $validated = $request->validate([
            'telefono' => 'nullable|string|max:20',
            'direccion' => 'nullable|string|max:255',
            'fecha_nacimiento' => 'nullable|date',
            'foto_perfil' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $studentDetail = $user->studentDetail;

        if (!$studentDetail) {
            return response()->json([
                'message' => 'No se encontraron detalles del estudiante'
            ], 404);
        }

        $studentDetail->update($validated);

        return response()->json([
            'message' => 'Información personal actualizada exitosamente',
            'student_detail' => $studentDetail->fresh()
        ]);
    }

    /**
     * Actualizar foto de perfil
     */
    public function updateProfilePhoto(Request $request)
    {
        try {
            $validated = $request->validate([
                'foto_perfil' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048', // 2MB máximo
            ]);

            $user = Auth::user();
            $studentDetail = $user->studentDetail;

            if (!$studentDetail) {
                return response()->json([
                    'message' => 'No se encontraron detalles del estudiante'
                ], 404);
            }

            $fotoPath = null;

            // Si se envía una imagen como archivo
            if ($request->hasFile('foto_perfil')) {
                $file = $request->file('foto_perfil');
                $filename = 'profile_' . $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
                
                // Guardar directamente en public/images/profiles (más confiable que storage)
                $publicPath = public_path('images/profiles');
                
                // Crear directorio si no existe
                if (!file_exists($publicPath)) {
                    mkdir($publicPath, 0755, true);
                }
                
                // Mover el archivo
                $file->move($publicPath, $filename);
                $fotoPath = '/images/profiles/' . $filename;
            } 
            // Si se envía como base64 (para compatibilidad)
            elseif ($request->has('foto_perfil') && is_string($request->foto_perfil)) {
                $base64Image = $request->foto_perfil;
                
                // Verificar si es una imagen base64 válida
                if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $matches)) {
                    $imageData = substr($base64Image, strpos($base64Image, ',') + 1);
                    $imageData = base64_decode($imageData);
                    $extension = $matches[1];
                    
                    // Validar extensión
                    if (!in_array($extension, ['jpeg', 'jpg', 'png', 'gif'])) {
                        return response()->json([
                            'message' => 'Formato de imagen no válido. Use JPEG, PNG o GIF.'
                        ], 422);
                    }
                    
                    $filename = 'profile_' . $user->id . '_' . time() . '.' . $extension;
                    $publicPath = public_path('images/profiles');
                    
                    // Crear directorio si no existe
                    if (!file_exists($publicPath)) {
                        mkdir($publicPath, 0755, true);
                    }
                    
                    $path = $publicPath . '/' . $filename;
                    file_put_contents($path, $imageData);
                    $fotoPath = '/images/profiles/' . $filename;
                } else {
                    // Si no es base64, asumir que es una URL o ruta
                    $fotoPath = $request->foto_perfil;
                }
            }

            if ($fotoPath) {
                // Eliminar foto anterior si existe
                if ($studentDetail->foto_perfil) {
                    // Si es una ruta de storage
                    if (strpos($studentDetail->foto_perfil, '/storage/profiles/') !== false) {
                        $oldPath = storage_path('app/public' . str_replace('/storage/', '', $studentDetail->foto_perfil));
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    // Si es una ruta de public/images/profiles
                    elseif (strpos($studentDetail->foto_perfil, '/images/profiles/') !== false) {
                        $oldPath = public_path($studentDetail->foto_perfil);
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                }

                $studentDetail->update([
                    'foto_perfil' => $fotoPath
                ]);
            }

            return response()->json([
                'message' => 'Foto de perfil actualizada exitosamente',
                'foto_perfil' => $studentDetail->fresh()->foto_perfil
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            \Log::error('Error actualizando foto de perfil: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'user_id' => Auth::id(),
            ]);
            
            return response()->json([
                'message' => 'Error al actualizar la foto de perfil: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Obtener el historial académico del estudiante autenticado
     */
    public function getAcademicHistory()
    {
        $user = Auth::user();
        $studentDetail = $user->studentDetail;
        
        if (!$studentDetail) {
            return response()->json([
                'message' => 'No se encontraron detalles del estudiante'
            ], 404);
        }

        $group = $studentDetail->group;
        
        // Obtener las materias del grupo del estudiante (carga académica) con calificaciones
        $academicLoads = [];
        if ($group) {
            // Obtener inscripciones del estudiante con calificaciones
            $inscripciones = \App\Models\Inscripcion::where('student_id', $user->id)
                ->with(['academicLoad.subject', 'calificacionesDetalle'])
                ->get()
                ->keyBy('academic_load_id');

            // Obtener el periodo académico activo
            $activePeriod = \App\Models\AcademicPeriod::active()->first();
            
            $query = \App\Models\AcademicLoad::where('group_id', $group->id);
            
            // Filtrar por periodo activo si existe
            if ($activePeriod) {
                $query->where('academic_period_id', $activePeriod->id);
            }
            
            $academicLoads = $query->with('subject')
                ->get()
                ->map(function ($load) use ($inscripciones, $user) {
                    $inscripcion = $inscripciones->get($load->id);
                    
                    // Obtener promedio final de la inscripción (promedio de las 3 unidades)
                    $calificacionFinal = null;
                    $porcentajeAsistencia = 0;
                    
                    // Obtener calificaciones por unidad (sistema dinámico)
                    $unidades = [];
                    $calificacionFinal = null;
                    $porcentajeAsistencia = 0;
                    
                    if ($inscripcion) {
                        // Obtener unidades configuradas para esta materia
                        $courseUnits = \App\Models\CourseUnit::where('academic_load_id', $load->id)
                            ->orderBy('id')
                            ->get();
                        
                        // Obtener todas las calificaciones del estudiante para esta materia
                        $calificacionesDetalle = $inscripcion->calificacionesDetalle()
                            ->with('courseUnit')
                            ->whereNotNull('calificacion_final_unidad')
                            ->get();
                        
                        $asistenciasPorUnidad = [];
                        
                        // Procesar cada unidad configurada
                        foreach ($courseUnits as $index => $courseUnit) {
                            $calificacionUnidad = $calificacionesDetalle->firstWhere('course_unit_id', $courseUnit->id);
                            
                            $calificacionUnidadValue = $calificacionUnidad 
                                ? (float) $calificacionUnidad->calificacion_final_unidad 
                                : null;
                            
                            // Calcular porcentaje de asistencia por unidad usando course_unit_id
                            $porcentajeAsistenciaUnidad = 100; // Por defecto 100%
                            try {
                                $calificacionService = app(\App\Services\CalificacionService::class);
                                $porcentajeAsistenciaUnidad = $calificacionService->calcularPorcentajeAsistencia(
                                    $user->id,
                                    $load->id,
                                    $courseUnit->id // Usar course_unit_id directamente
                                );
                            } catch (\Exception $e) {
                                \Log::warning('Error calculando asistencia unidad ' . $courseUnit->id . ': ' . $e->getMessage());
                                $porcentajeAsistenciaUnidad = 100; // Por defecto 100%
                            }
                            
                            // Usar U1, U2, U3, U4, etc. como clave principal para consistencia
                            $unidadKey = 'U' . ($index + 1);
                            $unidades[$unidadKey] = [
                                'calificacion' => $calificacionUnidadValue,
                                'asistencia' => round($porcentajeAsistenciaUnidad, 1),
                                'porcentaje' => $courseUnit->porcentaje,
                                'nombre' => $courseUnit->nombre, // Incluir nombre para referencia
                            ];
                            
                            // Acumular asistencias para promedio general
                            $asistenciasPorUnidad[] = $porcentajeAsistenciaUnidad;
                        }
                        
                        if (!empty($asistenciasPorUnidad)) {
                            $porcentajeAsistencia = round(array_sum($asistenciasPorUnidad) / count($asistenciasPorUnidad), 1);
                        }
                        
                        // Calcular promedio final usando el método del modelo Inscripcion
                        $calificacionFinal = $inscripcion->calcularPromedioFinal();
                    }
                    
                    return [
                        'id' => $load->id,
                        'subject_id' => $load->subject_id,
                        'materia' => $load->subject->nombre ?? 'Materia no encontrada',
                        'codigo' => $load->subject->codigo ?? '',
                        'teacher_name' => $load->teacher_name,
                        'calificacion' => $calificacionFinal,
                        'asistencia' => $porcentajeAsistencia,
                        'unidades' => $unidades, // Calificaciones por unidad (U1, U2, U3)
                    ];
                });
        }

        // Calcular cuatrimestre actual
        $now = now();
        $year = $now->year;
        $month = $now->month;
        $cuatrimestre = 1;
        if ($month >= 5 && $month <= 8) {
            $cuatrimestre = 2;
        } else if ($month >= 9) {
            $cuatrimestre = 3;
        }
        $cuatrimestreActual = "{$year}-{$cuatrimestre}";

        // Calcular avance de carrera (basado en el grado)
        $avanceCarrera = 0;
        if ($studentDetail->grado) {
            // Asumiendo que el grado es un número (ej: "4" para 4to cuatrimestre)
            $gradoNum = (int) $studentDetail->grado;
            // Asumiendo que una carrera tiene aproximadamente 12 cuatrimestres (3 años)
            $avanceCarrera = min(100, round(($gradoNum / 12) * 100));
        }

        return response()->json([
            'student_info' => [
                'nombre' => $user->name,
                'matricula' => $studentDetail->matricula,
                'carrera' => $studentDetail->carrera,
                'cuatrimestreActual' => $studentDetail->grado ? "{$studentDetail->grado}° Cuatrimestre" : 'No asignado',
                'promedioGeneral' => $studentDetail->promedio_general ?? 0.00,
                'avanceCarrera' => $avanceCarrera,
            ],
            'current_cuatrimestre' => [
                'periodo' => $cuatrimestreActual,
                'materias' => $academicLoads,
            ],
            // Preparado para historial completo cuando exista el sistema de calificaciones
            'historial' => [], // Se llenará cuando existan calificaciones históricas
        ]);
    }
}


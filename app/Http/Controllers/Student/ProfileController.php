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
}


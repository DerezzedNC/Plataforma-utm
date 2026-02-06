<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Area;
use App\Models\Career;
use App\Models\Group;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rules;

class TeacherController extends Controller
{
    /**
     * Listar todos los maestros
     */
    public function index()
    {
        $query = User::where('email', 'LIKE', '%@utmetropolitana.edu.mx')
            ->where('email', 'NOT LIKE', '%@alumno.%')
            ->where('email', 'NOT LIKE', '%@admin.%');
        
        // Intentar cargar relaciones si las tablas existen
        if (Schema::hasTable('teacher_areas') && Schema::hasTable('teacher_careers')) {
            $query->with(['areas', 'careers.area']);
        }
        
        // Cargar información de tutor si existe
        if (Schema::hasTable('groups')) {
            $query->with(['tutoredGroups']);
        }
        
        $teachers = $query->get();
        
        // Agregar información de si es tutor y qué grupos tiene
        $teachers->each(function ($teacher) {
            $teacher->is_tutor = $teacher->tutoredGroups && $teacher->tutoredGroups->count() > 0;
            if ($teacher->tutoredGroups && $teacher->tutoredGroups->count() > 0) {
                $teacher->tutor_groups = $teacher->tutoredGroups->map(function ($group) {
                    return [
                        'id' => $group->id,
                        'carrera' => $group->carrera,
                        'grado' => $group->grado,
                        'grupo' => $group->grupo,
                    ];
                })->toArray();
            } else {
                $teacher->tutor_groups = [];
            }
        });

        return response()->json($teachers);
    }

    /**
     * Crear un nuevo maestro
     * El correo se genera automáticamente usando: {apellido_paterno}.{nombre}@utmetropolitana.edu.mx
     */
    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|unique:users', // Opcional, se genera automáticamente
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ];
        
        // Agregar validaciones de áreas y carreras solo si las tablas existen
        if (Schema::hasTable('teacher_areas') && Schema::hasTable('areas')) {
            $rules['area_ids'] = 'nullable|array';
            $rules['area_ids.*'] = 'exists:areas,id';
        }
        
        if (Schema::hasTable('teacher_careers') && Schema::hasTable('careers')) {
            $rules['career_ids'] = 'nullable|array';
            $rules['career_ids.*'] = 'exists:careers,id';
        }
        
        $validated = $request->validate($rules);

        // Generar correo automáticamente usando apellido paterno y nombre
        // Formato: {apellido_paterno}.{nombre}@utmetropolitana.edu.mx
        $apellidoPaterno = $this->normalizeForEmail($validated['apellido_paterno']);
        // Tomar solo el primer nombre si hay múltiples nombres
        $nombreCompleto = explode(' ', $validated['name']);
        $primerNombre = $nombreCompleto[0];
        $nombre = $this->normalizeForEmail($primerNombre);
        
        $email = $validated['email'] ?? strtolower($apellidoPaterno . '.' . $nombre . '@utmetropolitana.edu.mx');
        
        // Verificar que el correo generado no exista
        if (User::where('email', $email)->exists()) {
            // Si existe, intentar agregar un número
            $counter = 1;
            $baseEmail = strtolower($apellidoPaterno . '.' . $nombre);
            do {
                $email = $baseEmail . $counter . '@utmetropolitana.edu.mx';
                $counter++;
            } while (User::where('email', $email)->exists() && $counter < 100);
        }

        $teacher = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        // Asignar áreas/sectores si se proporcionaron y las tablas existen
        if (Schema::hasTable('teacher_areas') && Schema::hasTable('teacher_careers')) {
            try {
                if (isset($validated['area_ids']) && !empty($validated['area_ids'])) {
                    $teacher->areas()->attach($validated['area_ids']);
                }

                // Asignar carreras si se proporcionaron
                if (isset($validated['career_ids']) && !empty($validated['career_ids'])) {
                    $teacher->careers()->attach($validated['career_ids']);
                }
                
                $teacher->load(['areas', 'careers.area']);
            } catch (\Exception $e) {
                // Si hay error al asignar, continuar sin asignar
                \Log::warning('No se pudieron asignar áreas/carreras al maestro: ' . $e->getMessage());
            }
        }

        // Asignar grupos como tutor si se proporcionaron
        if (Schema::hasTable('groups') && isset($validated['group_ids']) && !empty($validated['group_ids'])) {
            try {
                Group::whereIn('id', $validated['group_ids'])
                    ->update(['tutor_id' => $teacher->id]);
            } catch (\Exception $e) {
                \Log::warning('No se pudieron asignar grupos al maestro: ' . $e->getMessage());
            }
        }

        return response()->json($teacher, 201);
    }

    /**
     * Normalizar texto para usar en correo electrónico
     * Elimina acentos, espacios y caracteres especiales
     */
    private function normalizeForEmail(string $text): string
    {
        // Convertir a minúsculas
        $text = mb_strtolower($text, 'UTF-8');
        
        // Reemplazar acentos y caracteres especiales
        $text = str_replace(
            ['á', 'é', 'í', 'ó', 'ú', 'ñ', 'ü', ' '],
            ['a', 'e', 'i', 'o', 'u', 'n', 'u', ''],
            $text
        );
        
        // Eliminar caracteres que no sean letras o números
        $text = preg_replace('/[^a-z0-9]/', '', $text);
        
        return $text;
    }

    /**
     * Mostrar un maestro específico
     */
    public function show(string $teacher)
    {
        $query = User::query();
        
        // Intentar cargar relaciones si las tablas existen
        if (Schema::hasTable('teacher_areas') && Schema::hasTable('teacher_careers')) {
            $query->with(['areas', 'careers.area']);
        }
        
        // Cargar grupos como tutor si existe la tabla
        if (Schema::hasTable('groups')) {
            $query->with(['tutoredGroups']);
        }
        
        $user = $query->findOrFail($teacher);
        return response()->json($user);
    }

    /**
     * Actualizar un maestro
     */
    public function update(Request $request, string $teacher)
    {
        $user = User::findOrFail($teacher);
        
        $rules = [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|unique:users,email,' . $user->id,
        ];
        
        // Agregar validaciones de áreas y carreras solo si las tablas existen
        if (Schema::hasTable('teacher_areas') && Schema::hasTable('areas')) {
            $rules['area_ids'] = 'nullable|array';
            $rules['area_ids.*'] = 'exists:areas,id';
        }
        
        if (Schema::hasTable('teacher_careers') && Schema::hasTable('careers')) {
            $rules['career_ids'] = 'nullable|array';
            $rules['career_ids.*'] = 'exists:careers,id';
        }
        
        // Agregar validación de grupos si la tabla existe
        if (Schema::hasTable('groups')) {
            $rules['group_ids'] = 'nullable|array';
            $rules['group_ids.*'] = 'exists:groups,id';
        }
        
        $validated = $request->validate($rules);

        $user->update($validated);

        // Sincronizar áreas/sectores si las tablas existen
        if (Schema::hasTable('teacher_areas') && Schema::hasTable('teacher_careers')) {
            try {
                if (isset($validated['area_ids'])) {
                    $user->areas()->sync($validated['area_ids']);
                }

                // Sincronizar carreras
                if (isset($validated['career_ids'])) {
                    $user->careers()->sync($validated['career_ids']);
                }
                
                $user->load(['areas', 'careers.area']);
            } catch (\Exception $e) {
                // Si hay error al sincronizar, continuar sin sincronizar
                \Log::warning('No se pudieron sincronizar áreas/carreras del maestro: ' . $e->getMessage());
            }
        }

        // Sincronizar grupos como tutor
        if (Schema::hasTable('groups')) {
            try {
                // Primero, remover al maestro de todos los grupos que tenía asignados
                Group::where('tutor_id', $user->id)->update(['tutor_id' => null]);
                
                // Luego, asignar los nuevos grupos
                if (isset($validated['group_ids']) && !empty($validated['group_ids'])) {
                    Group::whereIn('id', $validated['group_ids'])
                        ->update(['tutor_id' => $user->id]);
                }
            } catch (\Exception $e) {
                \Log::warning('No se pudieron sincronizar grupos del maestro: ' . $e->getMessage());
            }
        }

        return response()->json($user);
    }

    /**
     * Eliminar un maestro
     */
    public function destroy(string $teacher)
    {
        $user = User::findOrFail($teacher);
        $user->delete();

        return response()->json(['message' => 'Maestro eliminado correctamente']);
    }

    /**
     * Obtener todas las áreas/sectores disponibles
     */
    public function getAreas()
    {
        $areas = Area::orderBy('nombre')->get();
        return response()->json($areas);
    }

    /**
     * Obtener todas las carreras disponibles
     */
    public function getCareers()
    {
        $careers = Career::with('area')->orderBy('nombre')->get();
        return response()->json($careers);
    }

    /**
     * Obtener carreras por área
     */
    public function getCareersByArea(Request $request)
    {
        $validated = $request->validate([
            'area_id' => 'required|exists:areas,id',
        ]);

        $careers = Career::where('area_id', $validated['area_id'])
            ->with('area')
            ->orderBy('nombre')
            ->get();

        return response()->json($careers);
    }

    /**
     * Obtener grupos disponibles para asignar como tutor
     * Filtrados por las carreras que el maestro imparte
     */
    public function getAvailableGroups(Request $request)
    {
        $validated = $request->validate([
            'teacher_id' => 'nullable|exists:users,id',
            'career_ids' => 'nullable|array',
            'career_ids.*' => 'exists:careers,id',
        ]);

        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        
        if (!$activePeriod) {
            return response()->json([
                'message' => 'No hay un periodo académico activo.',
            ], 422);
        }

        $query = Group::where('academic_period_id', $activePeriod->id);

        // Si se proporciona teacher_id, obtener sus carreras
        if (isset($validated['teacher_id'])) {
            $teacher = User::find($validated['teacher_id']);
            if ($teacher && Schema::hasTable('teacher_careers')) {
                $careerIds = $teacher->careers()->pluck('careers.id')->toArray();
                if (!empty($careerIds)) {
                    // Obtener nombres de carreras
                    $careerNames = Career::whereIn('id', $careerIds)->pluck('nombre')->toArray();
                    $query->whereIn('carrera', $careerNames);
                } else {
                    // Si no tiene carreras asignadas, no mostrar grupos
                    return response()->json([]);
                }
            } else {
                // Si no tiene carreras asignadas, no mostrar grupos
                return response()->json([]);
            }
        } elseif (isset($validated['career_ids']) && !empty($validated['career_ids'])) {
            // Si se proporcionan career_ids directamente, usarlos
            $careerNames = Career::whereIn('id', $validated['career_ids'])->pluck('nombre')->toArray();
            if (!empty($careerNames)) {
                $query->whereIn('carrera', $careerNames);
            } else {
                return response()->json([]);
            }
        } else {
            // Si no hay filtros, retornar todos los grupos del periodo activo
        }

        $groups = $query->orderBy('carrera')
            ->orderBy('grado')
            ->orderBy('grupo')
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->grado . '° ' . $group->grupo . ' - ' . $group->carrera,
                    'carrera' => $group->carrera,
                    'grado' => $group->grado,
                    'grupo' => $group->grupo,
                ];
            });

        return response()->json($groups);
    }
}

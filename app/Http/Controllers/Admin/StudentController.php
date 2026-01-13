<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\StudentDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class StudentController extends Controller
{
    /**
     * Listar todos los estudiantes
     */
    public function index()
    {
        $students = User::whereHas('studentDetail')
            ->with('studentDetail')
            ->get();

        return response()->json($students);
    }

    /**
     * Crear un nuevo estudiante
     * El correo se genera automáticamente usando la matrícula: {matricula}@alumno.utmetropolitana.edu.mx
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|string|email|unique:users', // Opcional, se genera automáticamente
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'matricula' => 'required|string|unique:student_details',
            'carrera' => 'required|string',
        ]);

        // Generar correo automáticamente usando la matrícula
        $email = $validated['email'] ?? $validated['matricula'] . '@alumno.utmetropolitana.edu.mx';
        
        // Verificar que el correo generado no exista (por si acaso)
        if (User::where('email', $email)->exists()) {
            return response()->json([
                'message' => 'El correo generado automáticamente ya existe. Por favor, verifique la matrícula.',
                'errors' => ['matricula' => ['La matrícula ya está asociada a un usuario.']]
            ], 422);
        }

        // Crear el usuario
        $user = User::create([
            'name' => $validated['name'],
            'email' => $email,
            'password' => Hash::make($validated['password']),
            'email_verified_at' => now(),
        ]);

        // Crear los detalles del estudiante
        $user->studentDetail()->create([
            'matricula' => $validated['matricula'],
            'carrera' => $validated['carrera'],
        ]);

        return response()->json($user->load('studentDetail'), 201);
    }

    /**
     * Mostrar un estudiante específico
     */
    public function show(string $student)
    {
        $user = User::findOrFail($student);
        return response()->json($user->load('studentDetail'));
    }

    /**
     * Actualizar un estudiante
     * Nota: Para cambiar el grupo de un estudiante, use el endpoint específico de grupos
     */
    public function update(Request $request, string $student)
    {
        $user = User::findOrFail($student);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|unique:users,email,' . $user->id,
            'matricula' => 'sometimes|string',
            'carrera' => 'sometimes|string',
            // group_id no se permite aquí - usar el endpoint específico de grupos
        ]);

        // Validar matrícula único si se está actualizando
        if (isset($validated['matricula']) && $user->studentDetail) {
            $request->validate([
                'matricula' => 'unique:student_details,matricula,' . $user->studentDetail->id,
            ]);
        }

        // Actualizar usuario
        $userData = [];
        if (isset($validated['name'])) {
            $userData['name'] = $validated['name'];
        }
        if (isset($validated['email'])) {
            $userData['email'] = $validated['email'];
        }
        if (!empty($userData)) {
            $user->update($userData);
        }

        // Actualizar detalles del estudiante
        if ($user->studentDetail) {
            $detailData = [];
            if (isset($validated['matricula'])) $detailData['matricula'] = $validated['matricula'];
            if (isset($validated['carrera'])) {
                $detailData['carrera'] = $validated['carrera'];
                // Si se cambia la carrera, remover del grupo actual
                if ($user->studentDetail->carrera !== $validated['carrera']) {
                    $detailData['group_id'] = null;
                    $detailData['grupo'] = null;
                    $detailData['grado'] = null;
                }
            }
            
            $user->studentDetail->update($detailData);
        }

        return response()->json($user->load('studentDetail'));
    }

    /**
     * Eliminar un estudiante
     */
    public function destroy(string $student)
    {
        $user = User::findOrFail($student);
        $user->delete();

        return response()->json(['message' => 'Estudiante eliminado correctamente']);
    }
}

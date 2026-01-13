<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class TeacherController extends Controller
{
    /**
     * Listar todos los maestros
     */
    public function index()
    {
        $teachers = User::where('email', 'LIKE', '%@utmetropolitana.edu.mx')
            ->where('email', 'NOT LIKE', '%@alumno.%')
            ->where('email', 'NOT LIKE', '%@admin.%')
            ->get();

        return response()->json($teachers);
    }

    /**
     * Crear un nuevo maestro
     * El correo se genera automáticamente usando: {apellido_paterno}.{nombre}@utmetropolitana.edu.mx
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'apellido_paterno' => 'required|string|max:255',
            'apellido_materno' => 'nullable|string|max:255',
            'email' => 'nullable|string|email|unique:users', // Opcional, se genera automáticamente
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

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
        $user = User::findOrFail($teacher);
        return response()->json($user);
    }

    /**
     * Actualizar un maestro
     */
    public function update(Request $request, string $teacher)
    {
        $user = User::findOrFail($teacher);
        
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

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
}

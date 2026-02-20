<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * Iniciar sesión y generar token de acceso
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(Request $request)
    {
        // Validar los datos de entrada
        $validator = Validator::make($request->all(), [
            'email' => [
                'required',
                'string',
                'email',
                function ($attribute, $value, $fail) {
                    // Validar que sea correo de alumno, maestro o admin de UTM
                    $isAlumno = str_ends_with($value, '@alumno.utmetropolitana.edu.mx');
                    $isAdmin = str_ends_with($value, '@admin.utmetropolitana.edu.mx');
                    $isMaestro = str_ends_with($value, '@utmetropolitana.edu.mx') && 
                                !str_contains($value, '@alumno.utmetropolitana.edu.mx') &&
                                !str_contains($value, '@admin.utmetropolitana.edu.mx');
                    
                    if (!$isAlumno && !$isMaestro && !$isAdmin) {
                        $fail('El correo debe terminar con uno de los siguientes: @alumno.utmetropolitana.edu.mx, @admin.utmetropolitana.edu.mx, @utmetropolitana.edu.mx.');
                    }
                }
            ],
            'password' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        // Intentar autenticar al usuario
        if (!Auth::attempt($request->only('email', 'password'))) {
            return response()->json([
                'message' => 'Credenciales incorrectas',
                'errors' => [
                    'email' => ['Las credenciales proporcionadas no son correctas.']
                ]
            ], 401);
        }

        $user = Auth::user();

        // Validar que el usuario sea un alumno
        if (!$user->isAlumno()) {
            Auth::logout();
            
            return response()->json([
                'message' => 'Acceso denegado',
                'error' => 'Esta aplicación móvil es exclusiva para alumnos. Los maestros y administradores deben usar el portal web.'
            ], 403);
        }

        // Generar token de acceso con Sanctum
        $token = $user->createToken('auth_token_mobile')->plainTextToken;

        // Obtener datos del estudiante
        $studentDetail = $user->studentDetail;

        // Preparar respuesta con datos del usuario
        $userData = [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'matricula' => $studentDetail ? $studentDetail->matricula : null,
            'carrera' => $studentDetail ? $studentDetail->carrera : null,
            'grado' => $studentDetail ? $studentDetail->grado : null,
            'grupo' => $studentDetail ? $studentDetail->grupo : null,
        ];

        return response()->json([
            'message' => 'Login exitoso',
            'token' => $token,
            'user' => $userData
        ], 200);
    }

    /**
     * Cerrar sesión y revocar token
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        // Revocar el token actual del usuario
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada exitosamente'
        ], 200);
    }
}

<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Rutas públicas (sin autenticación)
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas (requieren autenticación con Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    // Endpoint de prueba para obtener datos del usuario autenticado
    Route::get('/user', function (Request $request) {
        $user = $request->user();
        
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'rol' => $user->getRol(),
            'student_detail' => $user->studentDetail ? [
                'matricula' => $user->studentDetail->matricula,
                'carrera' => $user->studentDetail->carrera,
                'grado' => $user->studentDetail->grado,
                'grupo' => $user->studentDetail->grupo,
            ] : null,
        ]);
    });
    
    // Endpoint para cerrar sesión (revocar token)
    Route::post('/logout', [AuthController::class, 'logout']);
});

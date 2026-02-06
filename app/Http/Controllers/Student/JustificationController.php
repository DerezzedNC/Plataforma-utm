<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Justificacion;
use App\Models\Attendance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class JustificationController extends Controller
{
    /**
     * Crear una justificación de falta
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'attendance_id' => 'required|exists:attendances,id',
            'motivo' => 'required|string|max:1000',
            'evidencia' => 'nullable|file|mimes:jpeg,png,jpg,pdf|max:5120', // 5MB máximo
        ]);

        $user = Auth::user();
        
        // Verificar que la asistencia pertenece al estudiante
        $attendance = Attendance::findOrFail($validated['attendance_id']);
        if ($attendance->student_id !== $user->id) {
            return response()->json([
                'message' => 'No tienes permiso para justificar esta falta'
            ], 403);
        }

        // Verificar que la asistencia es una falta
        if ($attendance->estado !== 'falta') {
            return response()->json([
                'message' => 'Solo se pueden justificar faltas'
            ], 422);
        }

        // Verificar que no existe ya una justificación
        if (Justificacion::where('attendance_id', $attendance->id)->exists()) {
            return response()->json([
                'message' => 'Ya existe una justificación para esta falta'
            ], 422);
        }

        // Guardar evidencia si existe
        $evidenciaPath = null;
        if ($request->hasFile('evidencia')) {
            $file = $request->file('evidencia');
            $filename = 'justificacion_' . $attendance->id . '_' . time() . '.' . $file->getClientOriginalExtension();
            $evidenciaPath = $file->storeAs('justificaciones', $filename, 'public');
        }

        // Crear justificación
        $justificacion = Justificacion::create([
            'attendance_id' => $attendance->id,
            'student_id' => $user->id,
            'motivo' => $validated['motivo'],
            'evidencia' => $evidenciaPath,
            'estado' => 'pendiente',
        ]);

        return response()->json([
            'message' => 'Justificación enviada exitosamente',
            'justificacion' => $justificacion
        ], 201);
    }
}


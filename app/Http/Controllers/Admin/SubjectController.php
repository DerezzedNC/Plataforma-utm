<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\Career;
use Illuminate\Http\Request;

class SubjectController extends Controller
{
    /**
     * Listar todas las materias
     */
    public function index(Request $request)
    {
        $query = Subject::with('careers');

        // Filtros opcionales
        if ($request->has('grado')) {
            $query->where('grado', $request->grado);
        }
        if ($request->has('career_id')) {
            $query->whereHas('careers', function($q) use ($request) {
                $q->where('careers.id', $request->career_id);
            });
        }

        $subjects = $query->orderBy('grado')
            ->orderBy('nombre')
            ->get();

        return response()->json($subjects);
    }

    /**
     * Crear una nueva materia
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|unique:subjects',
            'grado' => 'required|integer|min:1|max:5',
            'career_ids' => 'required|array|min:1',
            'career_ids.*' => 'exists:careers,id',
        ], [
            'grado.min' => 'El grado debe ser entre 1 y 5.',
            'grado.max' => 'El grado debe ser entre 1 y 5.',
            'career_ids.required' => 'Debe seleccionar al menos una carrera.',
            'career_ids.min' => 'Debe seleccionar al menos una carrera.',
        ]);

        // Obtener el nombre de la primera carrera para el campo carrera (compatibilidad)
        $firstCareer = \App\Models\Career::find($validated['career_ids'][0]);
        $carreraNombre = $firstCareer ? $firstCareer->nombre : 'General';

        $subject = Subject::create([
            'nombre' => $validated['nombre'],
            'codigo' => $validated['codigo'],
            'grado' => $validated['grado'],
            'carrera' => $carreraNombre, // Campo de compatibilidad
        ]);

        // Asignar carreras
        $subject->careers()->sync($validated['career_ids']);

        return response()->json($subject->load('careers'), 201);
    }

    /**
     * Mostrar una materia específica
     */
    public function show(string $id)
    {
        $subject = Subject::with('careers')->findOrFail($id);
        return response()->json($subject);
    }

    /**
     * Actualizar una materia
     */
    public function update(Request $request, string $id)
    {
        $subject = Subject::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'codigo' => 'sometimes|string|unique:subjects,codigo,' . $id,
            'grado' => 'sometimes|integer|min:1|max:5',
            'career_ids' => 'sometimes|array|min:1',
            'career_ids.*' => 'exists:careers,id',
        ], [
            'grado.min' => 'El grado debe ser entre 1 y 5.',
            'grado.max' => 'El grado debe ser entre 1 y 5.',
            'career_ids.min' => 'Debe seleccionar al menos una carrera.',
        ]);

        // Actualizar carreras si se proporcionan
        if ($request->has('career_ids')) {
            $subject->careers()->sync($validated['career_ids']);
            
            // Actualizar el campo carrera con el nombre de la primera carrera (compatibilidad)
            $firstCareer = Career::find($validated['career_ids'][0]);
            if ($firstCareer) {
                $validated['carrera'] = $firstCareer->nombre;
            }
        }

        $subject->update([
            'nombre' => $validated['nombre'] ?? $subject->nombre,
            'codigo' => $validated['codigo'] ?? $subject->codigo,
            'grado' => $validated['grado'] ?? $subject->grado,
            'carrera' => $validated['carrera'] ?? $subject->carrera,
        ]);

        return response()->json($subject->load('careers'));
    }

    /**
     * Eliminar una materia
     */
    public function destroy(string $id)
    {
        $subject = Subject::findOrFail($id);
        $subject->delete();

        return response()->json(['message' => 'Materia eliminada correctamente']);
    }
}

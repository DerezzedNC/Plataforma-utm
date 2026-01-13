<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Career;
use App\Models\Area;
use Illuminate\Http\Request;

class CareerController extends Controller
{
    /**
     * Listar todas las carreras
     */
    public function index(Request $request)
    {
        $query = Career::with('area');

        // Filtro opcional por área
        if ($request->has('area_id')) {
            $query->where('area_id', $request->area_id);
        }

        $careers = $query->orderBy('area_id')
            ->orderBy('nombre')
            ->get();

        return response()->json($careers);
    }

    /**
     * Crear una nueva carrera
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'area_id' => 'required|exists:areas,id',
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|unique:careers',
        ]);

        $career = Career::create($validated);

        return response()->json($career->load('area'), 201);
    }

    /**
     * Mostrar una carrera específica
     */
    public function show(string $id)
    {
        $career = Career::with(['area', 'subjects'])->findOrFail($id);
        return response()->json($career);
    }

    /**
     * Actualizar una carrera
     */
    public function update(Request $request, string $id)
    {
        $career = Career::findOrFail($id);

        $validated = $request->validate([
            'area_id' => 'sometimes|exists:areas,id',
            'nombre' => 'sometimes|string|max:255',
            'codigo' => 'sometimes|string|unique:careers,codigo,' . $id,
        ]);

        $career->update($validated);

        return response()->json($career->load('area'));
    }

    /**
     * Eliminar una carrera
     */
    public function destroy(string $id)
    {
        $career = Career::findOrFail($id);
        $career->delete();

        return response()->json(['message' => 'Carrera eliminada correctamente']);
    }

    /**
     * Asignar materias a una carrera
     */
    public function assignSubjects(Request $request, string $id)
    {
        $career = Career::findOrFail($id);

        $validated = $request->validate([
            'subject_ids' => 'required|array',
            'subject_ids.*' => 'exists:subjects,id',
        ]);

        $career->subjects()->sync($validated['subject_ids']);

        return response()->json([
            'message' => 'Materias asignadas correctamente',
            'career' => $career->load(['area', 'subjects'])
        ]);
    }

    /**
     * Remover una materia de una carrera
     */
    public function removeSubject(string $careerId, string $subjectId)
    {
        $career = Career::findOrFail($careerId);
        $career->subjects()->detach($subjectId);

        return response()->json(['message' => 'Materia removida de la carrera correctamente']);
    }
}

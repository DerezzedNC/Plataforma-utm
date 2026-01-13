<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Area;
use Illuminate\Http\Request;

class AreaController extends Controller
{
    /**
     * Listar todas las áreas
     */
    public function index()
    {
        $areas = Area::withCount('careers')
            ->orderBy('nombre')
            ->get();

        return response()->json($areas);
    }

    /**
     * Crear una nueva área
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'nullable|string|unique:areas',
            'descripcion' => 'nullable|string',
        ]);

        $area = Area::create($validated);

        return response()->json($area, 201);
    }

    /**
     * Mostrar un área específica
     */
    public function show(string $id)
    {
        $area = Area::with('careers')->findOrFail($id);
        return response()->json($area);
    }

    /**
     * Actualizar un área
     */
    public function update(Request $request, string $id)
    {
        $area = Area::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'codigo' => 'nullable|string|unique:areas,codigo,' . $id,
            'descripcion' => 'nullable|string',
        ]);

        $area->update($validated);

        return response()->json($area);
    }

    /**
     * Eliminar un área
     */
    public function destroy(string $id)
    {
        $area = Area::findOrFail($id);
        $area->delete();

        return response()->json(['message' => 'Área eliminada correctamente']);
    }
}

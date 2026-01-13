<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Building;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    /**
     * Listar todos los edificios
     */
    public function index()
    {
        $buildings = Building::withCount('rooms')
            ->orderBy('nombre')
            ->get();

        return response()->json($buildings);
    }

    /**
     * Crear un nuevo edificio
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|unique:buildings',
        ]);

        $building = Building::create($validated);

        return response()->json($building, 201);
    }

    /**
     * Mostrar un edificio específico
     */
    public function show(string $id)
    {
        $building = Building::with('rooms')->findOrFail($id);
        return response()->json($building);
    }

    /**
     * Actualizar un edificio
     */
    public function update(Request $request, string $id)
    {
        $building = Building::findOrFail($id);

        $validated = $request->validate([
            'nombre' => 'sometimes|string|max:255',
            'codigo' => 'sometimes|string|unique:buildings,codigo,' . $id,
        ]);

        $building->update($validated);

        return response()->json($building);
    }

    /**
     * Eliminar un edificio
     */
    public function destroy(string $id)
    {
        $building = Building::findOrFail($id);
        $building->delete();

        return response()->json(['message' => 'Edificio eliminado correctamente']);
    }
}

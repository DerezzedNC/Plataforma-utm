<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AcademicPeriod;
use App\Services\StudentPromotionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeriodController extends Controller
{
    /**
     * Listar todos los periodos académicos
     */
    public function index()
    {
        $periods = AcademicPeriod::orderBy('start_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($periods);
    }

    /**
     * Crear un nuevo periodo académico
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:255|unique:academic_periods,code',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'is_active' => 'sometimes|boolean',
            'is_open_for_grades' => 'sometimes|boolean',
        ]);

        $period = AcademicPeriod::create($validated);

        return response()->json($period, 201);
    }

    /**
     * Activar un periodo y desactivar todos los demás
     * Asegura que solo haya un periodo activo a la vez usando transacción
     */
    public function toggleActive(string $id)
    {
        $period = AcademicPeriod::findOrFail($id);

        DB::transaction(function () use ($period) {
            // Desactivar todos los periodos
            AcademicPeriod::where('id', '!=', $period->id)
                ->update(['is_active' => false]);

            // Activar el periodo seleccionado
            $period->update(['is_active' => true]);
        });

        // Recargar el periodo con los datos actualizados
        $period->refresh();

        return response()->json([
            'message' => 'Periodo activado correctamente',
            'period' => $period
        ]);
    }

    /**
     * Cerrar un periodo académico y procesar la promoción de estudiantes
     * 
     * @param string $id ID del periodo a cerrar
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function closePeriod(string $id, Request $request)
    {
        $closedPeriod = AcademicPeriod::findOrFail($id);

        // Validar que el periodo no esté ya cerrado
        if (!$closedPeriod->is_active) {
            return response()->json([
                'message' => 'El periodo ya está cerrado o inactivo',
            ], 422);
        }

        // Validar datos del siguiente periodo
        $validated = $request->validate([
            'next_period_name' => 'required|string|max:255',
            'next_period_code' => 'required|string|max:255|unique:academic_periods,code',
            'next_period_start_date' => 'required|date|after:' . $closedPeriod->end_date,
            'next_period_end_date' => 'required|date|after:next_period_start_date',
        ]);

        try {
            DB::beginTransaction();

            // Crear el siguiente periodo académico
            $nextPeriod = AcademicPeriod::create([
                'name' => $validated['next_period_name'],
                'code' => $validated['next_period_code'],
                'start_date' => $validated['next_period_start_date'],
                'end_date' => $validated['next_period_end_date'],
                'is_active' => false, // No activar automáticamente
                'is_open_for_grades' => true,
            ]);

            // Cerrar el periodo actual
            $closedPeriod->update([
                'is_active' => false,
                'is_open_for_grades' => false, // Cerrar calificaciones
            ]);

            // Procesar promoción de estudiantes
            $promotionService = new StudentPromotionService();
            $results = $promotionService->processPromotion($closedPeriod, $nextPeriod);

            DB::commit();

            return response()->json([
                'message' => 'Periodo cerrado y promoción procesada correctamente',
                'closed_period' => $closedPeriod,
                'next_period' => $nextPeriod,
                'promotion_results' => $results,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error al cerrar el periodo: ' . $e->getMessage(),
            ], 500);
        }
    }
}


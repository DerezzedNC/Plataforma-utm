<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\Building;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class RoomController extends Controller
{
    /**
     * Listar todas las aulas/laboratorios
     */
    public function index(Request $request)
    {
        $query = Room::with('building');

        // Filtros opcionales
        if ($request->has('building_id')) {
            $query->where('building_id', $request->building_id);
        }
        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        $rooms = $query->orderBy('building_id')
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get();

        return response()->json($rooms);
    }

    /**
     * Crear una nueva aula/laboratorio
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'building_id' => 'required|exists:buildings,id',
            'nombre' => 'required|string|max:255',
            'codigo' => 'required|string|unique:rooms',
            'tipo' => 'required|string|in:Aula,Laboratorio,Taller,Auditorio',
        ], [
            'tipo.in' => 'El tipo debe ser: Aula, Laboratorio, Taller o Auditorio.',
        ]);

        $room = Room::create($validated);

        return response()->json($room->load('building'), 201);
    }

    /**
     * Mostrar una aula/laboratorio específica
     */
    public function show(string $id)
    {
        $room = Room::with('building')->findOrFail($id);
        return response()->json($room);
    }

    /**
     * Actualizar una aula/laboratorio
     */
    public function update(Request $request, string $id)
    {
        $room = Room::findOrFail($id);

        $validated = $request->validate([
            'building_id' => 'sometimes|exists:buildings,id',
            'nombre' => 'sometimes|string|max:255',
            'codigo' => 'sometimes|string|unique:rooms,codigo,' . $id,
            'tipo' => 'sometimes|string|in:Aula,Laboratorio,Taller,Auditorio',
        ], [
            'tipo.in' => 'El tipo debe ser: Aula, Laboratorio, Taller o Auditorio.',
        ]);

        $room->update($validated);

        return response()->json($room->load('building'));
    }

    /**
     * Eliminar una aula/laboratorio
     */
    public function destroy(string $id)
    {
        $room = Room::findOrFail($id);
        $room->delete();

        return response()->json(['message' => 'Aula/Laboratorio eliminado correctamente']);
    }

    /**
     * Obtener salones disponibles para un día y rango de tiempo específico
     * Excluye salones que ya tienen una clase asignada en ese horario
     */
    public function getAvailableRooms(Request $request)
    {
        $validated = $request->validate([
            'day' => 'required|string',
            'start_time' => 'required|date_format:H:i',
            'end_time' => 'required|date_format:H:i|after:start_time',
            'building_id' => 'nullable|exists:buildings,id',
            'exclude_schedule_id' => 'nullable|integer', // Para excluir el mismo horario al editar
        ]);

        // Obtener todos los salones
        $query = Room::with('building');

        // Filtrar por edificio si se proporciona
        if ($request->has('building_id') && $validated['building_id']) {
            $query->where('building_id', $validated['building_id']);
        }
        
        // Log: Verificar cuántos salones hay en total
        $totalRooms = Room::count();
        $totalRoomsInBuilding = $request->has('building_id') && $validated['building_id'] 
            ? Room::where('building_id', $validated['building_id'])->count() 
            : $totalRooms;
        
        Log::info('Salones disponibles - Inicio', [
            'total_rooms' => $totalRooms,
            'rooms_in_building' => $totalRoomsInBuilding,
            'building_id' => $validated['building_id'] ?? null,
        ]);

        // Normalizar el día de la semana para comparación (primera letra mayúscula)
        $normalizedDay = ucfirst(strtolower($validated['day']));
        
        // Obtener todos los horarios del día para debugging
        $allSchedulesForDay = \App\Models\Schedule::where('dia_semana', $normalizedDay)
            ->get(['id', 'aula', 'hora_inicio', 'hora_fin', 'materia', 'grupo']);
        
        // Obtener códigos de salones ocupados en ese horario
        // Verificar si hay traslape de horarios
        // Un horario está ocupado si hay cualquier traslape:
        // El horario existente comienza antes del fin del rango solicitado Y termina después del inicio del rango solicitado
        $startTime = $validated['start_time'];
        $endTime = $validated['end_time'];
        
        // Obtener todos los horarios del día y filtrar en PHP para mayor compatibilidad
        $schedulesForDay = \App\Models\Schedule::where('dia_semana', $normalizedDay)
            ->when($request->has('exclude_schedule_id') && $request->exclude_schedule_id, function($q) use ($request) {
                $q->where('id', '!=', $request->exclude_schedule_id);
            })
            ->whereNotNull('aula')
            ->where('aula', '!=', '')
            ->get(['id', 'aula', 'hora_inicio', 'hora_fin', 'materia', 'grupo']);
        
        // Filtrar horarios que se traslapan con el rango solicitado
        // Convertir horas a minutos para comparación más precisa
        $startMinutes = $this->timeToMinutes($startTime);
        $endMinutes = $this->timeToMinutes($endTime);
        
        $occupiedRoomCodes = $schedulesForDay->filter(function($schedule) use ($startMinutes, $endMinutes) {
            $scheduleStartMinutes = $this->timeToMinutes($schedule->hora_inicio);
            $scheduleEndMinutes = $this->timeToMinutes($schedule->hora_fin);
            
            // Verificar traslape: inicio_existente < fin_solicitado AND fin_existente > inicio_solicitado
            return $scheduleStartMinutes < $endMinutes && $scheduleEndMinutes > $startMinutes;
        })->pluck('aula')->unique()->values()->toArray();
        
        // Obtener los horarios que causan el conflicto para debugging
        $conflictingSchedules = $schedulesForDay->filter(function($schedule) use ($startMinutes, $endMinutes) {
            $scheduleStartMinutes = $this->timeToMinutes($schedule->hora_inicio);
            $scheduleEndMinutes = $this->timeToMinutes($schedule->hora_fin);
            
            return $scheduleStartMinutes < $endMinutes && $scheduleEndMinutes > $startMinutes;
        })->values();
        
        // Log para debugging
        Log::info('Salones disponibles - Debug', [
            'day' => $validated['day'],
            'normalized_day' => $normalizedDay,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'occupied_rooms' => $occupiedRoomCodes,
            'occupied_count' => count($occupiedRoomCodes),
            'total_schedules_for_day' => $allSchedulesForDay->count(),
            'all_schedules_for_day' => $allSchedulesForDay->map(function($s) {
                return [
                    'id' => $s->id,
                    'aula' => $s->aula,
                    'hora_inicio' => $s->hora_inicio,
                    'hora_fin' => $s->hora_fin,
                    'materia' => $s->materia,
                    'grupo' => $s->grupo,
                ];
            })->toArray(),
            'conflicting_schedules' => $conflictingSchedules->map(function($s) {
                return [
                    'id' => $s->id,
                    'aula' => $s->aula,
                    'hora_inicio' => $s->hora_inicio,
                    'hora_fin' => $s->hora_fin,
                    'materia' => $s->materia,
                    'grupo' => $s->grupo,
                ];
            })->toArray(),
            'time_comparison' => [
                'requested_start' => $startTime,
                'requested_end' => $endTime,
                'requested_start_minutes' => $startMinutes,
                'requested_end_minutes' => $endMinutes,
            ],
        ]);

        // Si no hay salones ocupados, devolver todos los salones (sin whereNotIn)
        if (empty($occupiedRoomCodes)) {
            $availableRooms = $query->orderBy('building_id')
                ->orderBy('tipo')
                ->orderBy('nombre')
                ->get();
            
            Log::info('No hay salones ocupados - devolviendo todos los salones', [
                'total_available' => $availableRooms->count(),
            ]);
        } else {
            // Obtener salones disponibles (excluyendo los ocupados)
            $availableRooms = $query->whereNotIn('codigo', $occupiedRoomCodes)
                ->orderBy('building_id')
                ->orderBy('tipo')
                ->orderBy('nombre')
                ->get();
            
            Log::info('Salones filtrados por ocupación', [
                'total_rooms_before_filter' => $query->count(),
                'occupied_rooms' => $occupiedRoomCodes,
                'available_after_filter' => $availableRooms->count(),
            ]);
        }
        
        // Log para debugging
        Log::info('Salones disponibles - Resultado', [
            'total_rooms' => $totalRooms,
            'rooms_in_building' => $totalRoomsInBuilding,
            'occupied_rooms' => $occupiedRoomCodes,
            'occupied_count' => count($occupiedRoomCodes),
            'available_count' => $availableRooms->count(),
            'building_filter' => $validated['building_id'] ?? null,
        ]);

        return response()->json($availableRooms);
    }
    
    /**
     * Convertir tiempo (HH:MM) a minutos totales para comparación
     */
    private function timeToMinutes($time)
    {
        if (empty($time)) {
            return 0;
        }
        
        // Si es un objeto Carbon/DateTime, obtener el formato H:i
        if ($time instanceof \DateTime || $time instanceof \Carbon\Carbon) {
            $time = $time->format('H:i');
        }
        
        // Si es un string, extraer horas y minutos
        $parts = explode(':', $time);
        $hours = (int)($parts[0] ?? 0);
        $minutes = (int)($parts[1] ?? 0);
        
        return ($hours * 60) + $minutes;
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AnnouncementController extends Controller
{
    /**
     * Listar todos los avisos
     */
    public function index()
    {
        $announcements = Announcement::with(['sender', 'recipients'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($announcements);
    }

    /**
     * Crear un nuevo aviso
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'priority' => 'required|in:baja,media,alta',
            'audience' => 'required|in:all_tutors,select_teachers',
            'selected_users' => 'required_if:audience,select_teachers|array',
            'selected_users.*' => 'exists:users,id',
        ]);

        // Determinar el target_type basado en audience
        $targetType = $validated['audience'] === 'all_tutors' 
            ? 'all_tutors' 
            : 'specific_teachers';

        // Crear el anuncio
        $announcement = Announcement::create([
            'title' => $validated['title'],
            'content' => $validated['content'],
            'sender_id' => auth()->id(),
            'target_type' => $targetType,
            'priority' => $validated['priority'],
        ]);

        // Determinar los destinatarios
        $recipientIds = [];

        if ($validated['audience'] === 'all_tutors') {
            // Buscar todos los tutor_id distintos de la tabla groups
            $recipientIds = Group::whereNotNull('tutor_id')
                ->distinct()
                ->pluck('tutor_id')
                ->toArray();
        } else {
            // Usar los IDs seleccionados
            $recipientIds = $validated['selected_users'] ?? [];
        }

        // Asociar el anuncio a los destinatarios en la tabla pivote
        if (!empty($recipientIds)) {
            $announcement->recipients()->attach($recipientIds);
        }

        // Cargar relaciones para la respuesta
        $announcement->load(['sender', 'recipients']);

        return response()->json([
            'message' => 'Aviso creado exitosamente',
            'announcement' => $announcement,
            'recipients_count' => count($recipientIds)
        ], 201);
    }

    /**
     * Mostrar un aviso específico
     */
    public function show(string $id)
    {
        $announcement = Announcement::with(['sender', 'recipients'])
            ->findOrFail($id);

        return response()->json($announcement);
    }

    /**
     * Obtener todos los maestros (para el selector)
     */
    public function getTeachers()
    {
        $teachers = User::where('email', 'LIKE', '%@utmetropolitana.edu.mx')
            ->where('email', 'NOT LIKE', '%@alumno.%')
            ->where('email', 'NOT LIKE', '%@admin.%')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json($teachers);
    }

    /**
     * Obtener todos los tutores (maestros que son tutores de algún grupo)
     */
    public function getTutors()
    {
        $tutors = User::whereHas('tutoredGroup')
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();

        return response()->json($tutors);
    }
}





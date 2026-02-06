<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Announcement;
use App\Models\GroupPost;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TutorController extends Controller
{
    /**
     * Obtener datos del dashboard del tutor
     */
    public function dashboard()
    {
        $user = auth()->user();

        // Obtener el periodo académico activo
        $activePeriod = \App\Models\AcademicPeriod::active()->first();
        
        if (!$activePeriod) {
            return response()->json([
                'message' => 'No hay un periodo académico activo. Por favor, contacta al administrador.',
            ], 422);
        }

        // Obtener el grupo del cual es tutor del periodo activo
        $group = Group::where('tutor_id', $user->id)
            ->where('academic_period_id', $activePeriod->id)
            ->with(['students', 'tutor'])
            ->first();

        // Obtener avisos no leídos asignados a este usuario
        $unreadAnnouncements = Announcement::whereHas('recipients', function($query) use ($user) {
            $query->where('user_id', $user->id)
                ->whereNull('read_at');
        })
        ->with(['sender'])
        ->orderBy('created_at', 'desc')
        ->get()
        ->map(function($announcement) use ($user) {
            // Obtener información del pivot (read_at, forwarded_at)
            $pivot = DB::table('announcement_user')
                ->where('announcement_id', $announcement->id)
                ->where('user_id', $user->id)
                ->first();

            return [
                'id' => $announcement->id,
                'title' => $announcement->title,
                'content' => $announcement->content,
                'priority' => $announcement->priority,
                'target_type' => $announcement->target_type,
                'sender' => $announcement->sender ? [
                    'id' => $announcement->sender->id,
                    'name' => $announcement->sender->name,
                    'email' => $announcement->sender->email,
                ] : null,
                'created_at' => $announcement->created_at,
                'read_at' => $pivot->read_at ?? null,
                'forwarded_at' => $pivot->forwarded_at ?? null,
                'is_read' => !is_null($pivot->read_at),
                'is_forwarded' => !is_null($pivot->forwarded_at),
            ];
        });

        return response()->json([
            'group' => $group ? [
                'id' => $group->id,
                'carrera' => $group->carrera,
                'grado' => $group->grado,
                'grupo' => $group->grupo,
                'group_name' => $group->grado . $group->grupo . '-' . substr($group->carrera, 0, 3),
                'students_count' => $group->students->count(),
            ] : null,
            'unread_announcements' => $unreadAnnouncements,
            'unread_count' => $unreadAnnouncements->count(),
        ]);
    }

    /**
     * Reenviar un aviso al grupo del tutor
     */
    public function forward(Request $request, $announcementId)
    {
        $user = auth()->user();

        // Verificar que el usuario sea tutor de un grupo
        $group = Group::where('tutor_id', $user->id)->first();

        if (!$group) {
            return response()->json([
                'message' => 'No eres tutor de ningún grupo'
            ], 403);
        }

        // Verificar que el aviso existe y está asignado al usuario
        $announcement = Announcement::findOrFail($announcementId);

        $pivot = DB::table('announcement_user')
            ->where('announcement_id', $announcementId)
            ->where('user_id', $user->id)
            ->first();

        if (!$pivot) {
            return response()->json([
                'message' => 'Este aviso no está asignado a ti'
            ], 403);
        }

        // Verificar si ya fue reenviado
        if ($pivot->forwarded_at) {
            return response()->json([
                'message' => 'Este aviso ya fue reenviado anteriormente',
                'forwarded_at' => $pivot->forwarded_at
            ], 400);
        }

        // Crear el post en el grupo
        $groupPost = GroupPost::create([
            'group_id' => $group->id,
            'announcement_id' => $announcement->id,
            'title' => $announcement->title,
            'content' => $announcement->content,
            'posted_by' => $user->id,
        ]);

        // Marcar forwarded_at en la tabla pivote
        DB::table('announcement_user')
            ->where('announcement_id', $announcementId)
            ->where('user_id', $user->id)
            ->update(['forwarded_at' => now()]);

        return response()->json([
            'message' => 'Aviso reenviado exitosamente a tu grupo',
            'group_post' => $groupPost,
            'forwarded_at' => now(),
        ], 200);
    }

    /**
     * Marcar un aviso como leído
     */
    public function markAsRead($announcementId)
    {
        $user = auth()->user();

        $pivot = DB::table('announcement_user')
            ->where('announcement_id', $announcementId)
            ->where('user_id', $user->id)
            ->first();

        if (!$pivot) {
            return response()->json([
                'message' => 'Este aviso no está asignado a ti'
            ], 403);
        }

        // Si ya está leído, no hacer nada
        if ($pivot->read_at) {
            return response()->json([
                'message' => 'Este aviso ya fue marcado como leído',
                'read_at' => $pivot->read_at
            ]);
        }

        // Marcar como leído
        DB::table('announcement_user')
            ->where('announcement_id', $announcementId)
            ->where('user_id', $user->id)
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'Aviso marcado como leído',
            'read_at' => now(),
        ]);
    }
}





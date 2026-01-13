<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Announcement extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'sender_id',
        'target_type',
        'priority',
    ];

    /**
     * Relación con el usuario que envió el aviso (sender)
     */
    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Relación con los usuarios que recibieron el aviso (recipients)
     */
    public function recipients()
    {
        return $this->belongsToMany(User::class, 'announcement_user')
            ->withPivot('read_at', 'forwarded_at')
            ->withTimestamps();
    }

    /**
     * Scope para filtrar avisos por prioridad
     */
    public function scopePriority($query, string $priority)
    {
        return $query->where('priority', $priority);
    }

    /**
     * Scope para filtrar avisos por tipo de destino
     */
    public function scopeTargetType($query, string $targetType)
    {
        return $query->where('target_type', $targetType);
    }

    /**
     * Verificar si un usuario específico ha leído el aviso
     */
    public function isReadBy(User $user): bool
    {
        return $this->recipients()
            ->where('user_id', $user->id)
            ->whereNotNull('read_at')
            ->exists();
    }

    /**
     * Verificar si un usuario específico ha reenviado el aviso
     */
    public function isForwardedBy(User $user): bool
    {
        return $this->recipients()
            ->where('user_id', $user->id)
            ->whereNotNull('forwarded_at')
            ->exists();
    }

    /**
     * Marcar el aviso como leído por un usuario
     */
    public function markAsReadBy(User $user): void
    {
        $this->recipients()->updateExistingPivot($user->id, [
            'read_at' => now(),
        ]);
    }

    /**
     * Marcar el aviso como reenviado por un usuario
     */
    public function markAsForwardedBy(User $user): void
    {
        $this->recipients()->updateExistingPivot($user->id, [
            'forwarded_at' => now(),
        ]);
    }
}


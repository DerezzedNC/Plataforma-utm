<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Determina si el usuario es un maestro basado en su correo electrónico
     *
     * @return bool
     */
    public function isMaestro(): bool
    {
        // Maestros: correos que terminan en @utmetropolitana.edu.mx (pero NO @alumno ni @admin)
        return str_ends_with($this->email, '@utmetropolitana.edu.mx') && 
               !str_contains($this->email, '@alumno.utmetropolitana.edu.mx') &&
               !str_contains($this->email, '@admin.utmetropolitana.edu.mx');
    }

    /**
     * Determina si el usuario es un alumno basado en su correo electrónico
     *
     * @return bool
     */
    public function isAlumno(): bool
    {
        // Alumnos: correos que terminan en @alumno.utmetropolitana.edu.mx
        return str_ends_with($this->email, '@alumno.utmetropolitana.edu.mx');
    }

    /**
     * Obtiene el rol del usuario como string
     *
     * @return string
     */
    public function getRol(): string
    {
        if ($this->isAdmin()) {
            return 'admin';
        } elseif ($this->isAlumno()) {
            return 'alumno';
        } elseif ($this->isMaestro()) {
            return 'maestro';
        } else {
            return 'desconocido'; // Para correos que no siguen ninguna de las dos normas
        }
    }

    /**
     * Determina si el usuario es un administrador basado en su correo electrónico
     *
     * @return bool
     */
    public function isAdmin(): bool
    {
        // Administradores: correos que terminan en @admin.utmetropolitana.edu.mx
        return str_ends_with($this->email, '@admin.utmetropolitana.edu.mx');
    }

    /**
     * Relación con los detalles del estudiante
     */
    public function studentDetail()
    {
        return $this->hasOne(StudentDetail::class);
    }

    /**
     * Relación con los documentos del estudiante
     */
    public function documents()
    {
        return $this->hasMany(Document::class, 'student_id');
    }

    /**
     * Relación con el grupo del cual es tutor (un maestro puede ser tutor de un grupo)
     */
    public function tutoredGroup()
    {
        return $this->hasOne(Group::class, 'tutor_id');
    }

    /**
     * Relación con los avisos que ha recibido (maestros/tutores)
     */
    public function announcements()
    {
        return $this->belongsToMany(Announcement::class, 'announcement_user')
            ->withPivot('read_at', 'forwarded_at')
            ->withTimestamps();
    }

    /**
     * Relación con los avisos que ha enviado (como sender)
     */
    public function sentAnnouncements()
    {
        return $this->hasMany(Announcement::class, 'sender_id');
    }

    /**
     * Relación con los posts que ha publicado en grupos (como tutor)
     */
    public function groupPosts()
    {
        return $this->hasMany(GroupPost::class, 'posted_by');
    }
}

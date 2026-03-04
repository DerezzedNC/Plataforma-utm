<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Justificacion extends Model
{
    use HasFactory;

    // Forzar el nombre correcto de la tabla en español
    protected $table = 'justificaciones';

    protected $fillable = [
        'attendance_id',
        'student_id',
        'motivo',
        'evidencia',
        'estado',
        'observacion_admin',
        'revisado_por',
        'revisado_en',
    ];

    protected $casts = [
        'revisado_en' => 'datetime',
    ];

    /**
     * Relación con la asistencia
     */
    public function attendance()
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * Relación con el estudiante
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Relación con el administrador que revisó
     */
    public function revisadoPor()
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}


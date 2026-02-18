<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'schedule_id',
        'fecha',
        'unidad',
        'course_unit_id',
        'estado',
        'presente', // Para compatibilidad con estructura antigua
        'observaciones', // Columna real en la base de datos
        'teacher_id',
    ];

    protected $casts = [
        'fecha' => 'date',
        'unidad' => 'integer',
    ];

    /**
     * Relación con el estudiante
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Relación con el horario
     */
    public function schedule()
    {
        return $this->belongsTo(Schedule::class);
    }

    /**
     * Relación con el maestro
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Relación con justificaciones
     */
    public function justificacion()
    {
        return $this->hasOne(Justificacion::class, 'attendance_id');
    }

    /**
     * Relación con la unidad del curso
     */
    public function courseUnit()
    {
        return $this->belongsTo(CourseUnit::class);
    }
}


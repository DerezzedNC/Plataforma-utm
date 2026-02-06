<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Schedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_period_id',
        'carrera',
        'grupo',
        'materia',
        'profesor',
        'dia_semana',
        'hora_inicio',
        'hora_fin',
        'aula',
        'tipo',
    ];

    protected $casts = [
        'hora_inicio' => 'datetime:H:i',
        'hora_fin' => 'datetime:H:i',
    ];

    /**
     * Relación con asistencias
     */
    public function attendances()
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Relación con el periodo académico
     */
    public function period()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }
}

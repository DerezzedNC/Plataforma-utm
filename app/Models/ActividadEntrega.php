<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActividadEntrega extends Model
{
    use HasFactory;

    protected $table = 'actividades_entregas';

    protected $fillable = [
        'actividad_id',
        'student_id',
        'calificacion_obtenida',
        'observaciones',
        'calificado_en',
        'calificado_por',
    ];

    protected $casts = [
        'calificacion_obtenida' => 'decimal:2',
        'calificado_en' => 'datetime',
    ];

    /**
     * Boot del modelo - actualizar calificaciones automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::saved(function ($entrega) {
            try {
                // Cargar la actividad con su academic_load_id
                $actividad = $entrega->actividad;
                if ($actividad && $actividad->categoria === 'TAREA') {
                    // Solo recalcular si es una actividad de TAREA
                    \App\Services\GradeCalculatorService::recalculateFromActivityUpdate(
                        $entrega->student_id,
                        $actividad->academic_load_id,
                        $actividad->unidad
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Error actualizando calificación desde entrega: ' . $e->getMessage());
            }
        });

        static::deleted(function ($entrega) {
            try {
                // Cargar la actividad con su academic_load_id
                $actividad = $entrega->actividad;
                if ($actividad && $actividad->categoria === 'TAREA') {
                    // Solo recalcular si es una actividad de TAREA
                    \App\Services\GradeCalculatorService::recalculateFromActivityUpdate(
                        $entrega->student_id,
                        $actividad->academic_load_id,
                        $actividad->unidad
                    );
                }
            } catch (\Exception $e) {
                \Log::error('Error actualizando calificación al eliminar entrega: ' . $e->getMessage());
            }
        });
    }

    /**
     * Relación con la actividad
     */
    public function actividad()
    {
        return $this->belongsTo(Actividad::class, 'actividad_id');
    }

    /**
     * Relación con el estudiante
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Relación con el maestro que calificó
     */
    public function calificadoPor()
    {
        return $this->belongsTo(User::class, 'calificado_por');
    }
}


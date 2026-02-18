<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\CustomRounding;

class CalificacionDetalle extends Model
{
    use HasFactory, CustomRounding;

    protected $table = 'calificaciones_detalle';

    protected $fillable = [
        'student_id',
        'course_unit_id',
        'saber',
        'saber_hacer_convivir',
        'calificacion_final_unidad',
        // Mantener inscripcion_id para compatibilidad si es necesario
        'inscripcion_id',
    ];

    protected $casts = [
        'saber' => 'integer',
        'saber_hacer_convivir' => 'integer',
        'calificacion_final_unidad' => 'decimal:2',
    ];

    /**
     * Relación con el estudiante
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Relación con la unidad del curso
     */
    public function courseUnit()
    {
        return $this->belongsTo(CourseUnit::class, 'course_unit_id');
    }

    /**
     * Relación con la inscripción (mantener para compatibilidad si es necesario)
     */
    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    /**
     * Boot del modelo - calcular calificacion_final_unidad automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($calificacion) {
            $calificacion->calcularCalificacionFinal();
        });
    }

    /**
     * Calcular la calificación final de la unidad
     * Modelo 60-40: Saber * 0.6 + Saber Hacer * 0.4
     */
    public function calcularCalificacionFinal(): void
    {
        $saber = $this->saber ?? 0;
        $saberHacer = $this->saber_hacer_convivir ?? 0;
        
        // Calcular con fórmula 60-40: Saber * 0.6 + Saber Hacer * 0.4
        if ($saber > 0 || $saberHacer > 0) {
            $calificacionFinal = ($saber * 0.6) + ($saberHacer * 0.4);
            $this->calificacion_final_unidad = round($calificacionFinal, 2);
        } else {
            $this->calificacion_final_unidad = null;
        }
    }
}


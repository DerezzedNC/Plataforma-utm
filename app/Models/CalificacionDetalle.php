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
        'inscripcion_id',
        'unidad',
        'score_tareas',
        'score_examen',
        'score_conducta',
        'promedio_unidad',
        'derecho_examen',
    ];

    protected $casts = [
        'score_tareas' => 'decimal:2',
        'score_examen' => 'decimal:2',
        'score_conducta' => 'decimal:2',
        'promedio_unidad' => 'decimal:2',
        'derecho_examen' => 'boolean',
    ];

    /**
     * Relación con la inscripción
     */
    public function inscripcion()
    {
        return $this->belongsTo(Inscripcion::class, 'inscripcion_id');
    }

    /**
     * Boot del modelo - calcular promedio_unidad automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($calificacion) {
            $calificacion->calcularPromedioUnidad();
        });
    }

    /**
     * Calcular promedio de unidad con ponderación:
     * - Tareas: 40% (calculado desde actividades)
     * - Examen: 50% (0-100 puntos)
     * - Conducta: 10% (0-100 puntos)
     * 
     * Nota: Este método ya no se usa automáticamente porque ahora usamos
     * GradeCalculatorService::recalculateUnitAverage() que maneja todo el cálculo
     */
    public function calcularPromedioUnidad(): void
    {
        // Este método se mantiene por compatibilidad pero ahora el cálculo
        // se hace en GradeCalculatorService::recalculateUnitAverage()
        // No hacer nada aquí porque el cálculo ya se hace en el servicio
    }

    /**
     * Accessor para promedio_unidad con redondeo personalizado
     * Nota: El redondeo ya se aplica en calcularPromedioUnidad()
     */
    public function getPromedioUnidadAttribute($value)
    {
        return $value;
    }
}


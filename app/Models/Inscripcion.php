<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Inscripcion extends Model
{
    use HasFactory;

    protected $table = 'inscripciones';

    protected $fillable = [
        'student_id',
        'academic_period_id',
        'cuatrimestre', // Formato: 2025-1, 2025-2, etc.
        'grupo',
        'status',
        'promedio_final',
        // Mantener academic_load_id para compatibilidad si existe
        'academic_load_id',
        'aprobado',
    ];

    protected $casts = [
        'promedio_final' => 'decimal:2',
        'aprobado' => 'boolean',
    ];

    /**
     * Relación con el estudiante
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Relación con el estudiante (alias para compatibilidad)
     */
    public function alumno()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Relación con el periodo académico
     */
    public function periodo()
    {
        return $this->belongsTo(AcademicPeriod::class, 'academic_period_id');
    }

    /**
     * Relación con la carga académica (materia + grupo) - mantener para compatibilidad
     */
    public function academicLoad()
    {
        return $this->belongsTo(AcademicLoad::class);
    }

    /**
     * Scope para traer solo inscripciones activas (status = 'cursando')
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'cursando');
    }

    /**
     * Relación con las calificaciones detalle
     */
    public function calificacionesDetalle()
    {
        return $this->hasMany(CalificacionDetalle::class, 'inscripcion_id');
    }

    /**
     * Obtener calificación de una unidad específica
     */
    public function getCalificacionUnidad(int $unidad)
    {
        return $this->calificacionesDetalle()
            ->where('unidad', $unidad)
            ->first();
    }

    /**
     * Calcular promedio final basado en las 3 unidades
     */
    public function calcularPromedioFinal(): float
    {
        $calificaciones = $this->calificacionesDetalle()
            ->whereNotNull('promedio_unidad')
            ->get();

        if ($calificaciones->isEmpty()) {
            return 0.00;
        }

        $suma = $calificaciones->sum('promedio_unidad');
        $promedio = $suma / $calificaciones->count();

        // Aplicar redondeo personalizado usando el trait
        $trait = new class {
            use \App\Traits\CustomRounding;
        };
        return $trait->customRound($promedio, 2);
    }
}


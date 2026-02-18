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
     * Calcular promedio final basado en las unidades dinámicas con sus porcentajes
     * La calificación final se calcula como la suma ponderada de las calificaciones finales
     * de cada unidad multiplicadas por su porcentaje
     */
    public function calcularPromedioFinal(): float
    {
        if (!$this->academic_load_id) {
            return 0.00;
        }

        // Obtener todas las unidades del curso
        $courseUnits = \App\Models\CourseUnit::where('academic_load_id', $this->academic_load_id)->get();
        
        if ($courseUnits->isEmpty()) {
            return 0.00;
        }

        $sumaPonderada = 0;
        $sumaPorcentajes = 0;

        foreach ($courseUnits as $courseUnit) {
            // Obtener la calificación del estudiante para esta unidad
            $calificacion = \App\Models\CalificacionDetalle::where('student_id', $this->student_id)
                ->where('course_unit_id', $courseUnit->id)
                ->first();

            if ($calificacion && $calificacion->calificacion_final_unidad !== null) {
                // Calificación final de la unidad (0-100) multiplicada por el porcentaje de la unidad
                $sumaPonderada += ($calificacion->calificacion_final_unidad * $courseUnit->porcentaje) / 100;
                $sumaPorcentajes += $courseUnit->porcentaje;
            }
        }

        // Si no hay calificaciones, devolver 0
        if ($sumaPorcentajes == 0) {
            return 0.00;
        }

        // El promedio final es la suma ponderada dividida entre 100 (ya que los porcentajes suman 100)
        $promedio = ($sumaPonderada / $sumaPorcentajes) * 100;

        // Aplicar redondeo personalizado usando el trait
        $trait = new class {
            use \App\Traits\CustomRounding;
        };
        return $trait->customRound($promedio, 2);
    }
}


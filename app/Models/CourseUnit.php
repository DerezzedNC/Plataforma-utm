<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CourseUnit extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_load_id',
        'nombre',
        'porcentaje',
    ];

    protected $casts = [
        'porcentaje' => 'integer',
    ];

    /**
     * Relación con la carga académica (materia)
     */
    public function academicLoad()
    {
        return $this->belongsTo(AcademicLoad::class);
    }

    /**
     * Relación con las calificaciones
     */
    public function calificaciones()
    {
        return $this->hasMany(CalificacionDetalle::class, 'course_unit_id');
    }

    /**
     * Validar que la suma de porcentajes de todas las unidades de una materia sea 100%
     * Este método puede ser usado antes de guardar para validar
     */
    public static function validarSumaPorcentajes(int $academicLoadId): bool
    {
        $suma = self::where('academic_load_id', $academicLoadId)->sum('porcentaje');
        return $suma === 100;
    }
}

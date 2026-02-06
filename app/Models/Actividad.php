<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Actividad extends Model
{
    use HasFactory;

    /**
     * Nombre de la tabla en la base de datos
     */
    protected $table = 'actividades';

    /**
     * Campos que pueden ser asignados masivamente
     */
    protected $fillable = [
        'academic_load_id',
        'titulo',
        'descripcion',
        'unidad',
        'valor_maximo',
        'categoria',
        'fecha_limite',
        'activa',
    ];

    protected $casts = [
        'valor_maximo' => 'decimal:2',
        'unidad' => 'integer',
        'activa' => 'boolean',
        'fecha_limite' => 'date',
    ];

    /**
     * Relación con la carga académica
     */
    public function academicLoad()
    {
        return $this->belongsTo(AcademicLoad::class);
    }

    /**
     * Relación con las entregas de los estudiantes
     */
    public function entregas()
    {
        return $this->hasMany(ActividadEntrega::class, 'actividad_id');
    }

    /**
     * Obtener entrega de un estudiante específico
     */
    public function getEntregaEstudiante($studentId)
    {
        return $this->entregas()->where('student_id', $studentId)->first();
    }

    /**
     * Scope para filtrar por unidad
     */
    public function scopePorUnidad($query, int $unidad)
    {
        return $query->where('unidad', $unidad);
    }

    /**
     * Scope para filtrar por categoría
     */
    public function scopePorCategoria($query, string $categoria)
    {
        return $query->where('categoria', $categoria);
    }

    /**
     * Scope para actividades activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Subject extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'grado',
        'carrera', // Mantener por compatibilidad temporal
        'career_id', // Nueva relación
        'creditos',
        'descripcion',
    ];

    /**
     * Relación many-to-many con carreras
     */
    public function careers()
    {
        return $this->belongsToMany(Career::class, 'career_subject');
    }
}

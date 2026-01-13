<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    use HasFactory;

    protected $fillable = [
        'teacher_id',
        'tipo',
        'nombre',
        'descripcion',
        'tiempo_duracion',
        'costo',
        'link',
        'aula',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'costo' => 'decimal:2',
    ];

    /**
     * Relación con el maestro
     */
    public function teacher()
    {
        return $this->belongsTo(User::class, 'teacher_id');
    }

    /**
     * Relación many-to-many con carreras
     */
    public function careers()
    {
        return $this->belongsToMany(Career::class, 'course_career');
    }
}


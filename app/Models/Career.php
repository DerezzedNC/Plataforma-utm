<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Career extends Model
{
    use HasFactory;

    protected $fillable = [
        'area_id',
        'nombre',
        'codigo',
    ];

    /**
     * Relación con el área
     */
    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    /**
     * Relación many-to-many con materias
     */
    public function subjects()
    {
        return $this->belongsToMany(Subject::class, 'career_subject');
    }

    /**
     * Relación many-to-many con cursos
     */
    public function courses()
    {
        return $this->belongsToMany(Course::class, 'course_career');
    }
}

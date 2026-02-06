<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
    ];

    /**
     * Relación con las carreras del área
     */
    public function careers()
    {
        return $this->hasMany(Career::class);
    }

    /**
     * Relación many-to-many con maestros
     */
    public function teachers()
    {
        return $this->belongsToMany(User::class, 'teacher_areas', 'area_id', 'teacher_id');
    }
}

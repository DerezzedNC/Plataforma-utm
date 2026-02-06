<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicPeriod extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'start_date',
        'end_date',
        'is_active',
        'is_open_for_grades',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_active' => 'boolean',
        'is_open_for_grades' => 'boolean',
    ];

    /**
     * Relación con las cargas académicas
     */
    public function academicLoads()
    {
        return $this->hasMany(AcademicLoad::class);
    }

    /**
     * Relación con los grupos
     */
    public function groups()
    {
        return $this->hasMany(Group::class);
    }

    /**
     * Relación con los horarios
     */
    public function schedules()
    {
        return $this->hasMany(Schedule::class);
    }

    /**
     * Scope para obtener el periodo activo
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}


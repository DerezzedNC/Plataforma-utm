<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    use HasFactory;

    protected $fillable = [
        'carrera',
        'grado',
        'grupo',
        'tutor_id',
    ];

    /**
     * Relación con los estudiantes del grupo
     */
    public function students()
    {
        return $this->hasMany(StudentDetail::class);
    }

    /**
     * Relación con el tutor del grupo (User que es maestro)
     */
    public function tutor()
    {
        return $this->belongsTo(User::class, 'tutor_id');
    }

    /**
     * Relación con los posts del grupo
     */
    public function posts()
    {
        return $this->hasMany(GroupPost::class);
    }
}

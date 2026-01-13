<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AcademicLoad extends Model
{
    use HasFactory;

    protected $fillable = [
        'group_id',
        'subject_id',
        'teacher_name',
    ];

    /**
     * Relación con el grupo
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * Relación con la materia
     */
    public function subject()
    {
        return $this->belongsTo(Subject::class);
    }
}



<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'matricula',
        'carrera',
        'grado',
        'grupo',
        'group_id',
        'telefono',
        'direccion',
        'fecha_nacimiento',
        'foto_perfil',
        'promedio_general',
        'creditos_totales',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'promedio_general' => 'decimal:2',
    ];

    /**
     * Relación con el usuario
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Relación con el grupo
     */
    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}

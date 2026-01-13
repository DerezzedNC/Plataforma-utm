<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Building extends Model
{
    use HasFactory;

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
    ];

    /**
     * Relación con las aulas/laboratorios del edificio
     */
    public function rooms()
    {
        return $this->hasMany(Room::class);
    }
}

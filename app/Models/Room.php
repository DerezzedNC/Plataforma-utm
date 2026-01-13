<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'building_id',
        'nombre',
        'codigo',
        'tipo',
        'capacidad',
        'equipamiento',
    ];

    /**
     * Relación con el edificio
     */
    public function building()
    {
        return $this->belongsTo(Building::class);
    }
}

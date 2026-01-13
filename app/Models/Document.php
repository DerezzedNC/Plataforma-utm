<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'student_id',
        'tipo_documento',
        'motivo',
        'estado',
        'solicitado_en',
        'listo_en',
        'entregado_en',
        'administrador_id',
        'observaciones',
    ];

    protected $attributes = [
        'estado' => 'pendiente_revisar',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($document) {
            if (!$document->solicitado_en) {
                $document->solicitado_en = now();
            }
            if (!$document->estado) {
                $document->estado = 'pendiente_revisar';
            }
        });
    }

    protected $casts = [
        'solicitado_en' => 'datetime',
        'listo_en' => 'datetime',
        'entregado_en' => 'datetime',
    ];

    /**
     * Relación con el estudiante
     */
    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    /**
     * Relación con el administrador que procesa
     */
    public function administrador()
    {
        return $this->belongsTo(User::class, 'administrador_id');
    }
}

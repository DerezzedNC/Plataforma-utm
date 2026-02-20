<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('calificaciones_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inscripcion_id')->constrained('inscripciones')->onDelete('cascade');
            $table->tinyInteger('unidad'); // 1, 2, 3
            $table->decimal('score_tareas', 5, 2)->default(0.00); // 40%
            $table->decimal('score_examen', 5, 2)->nullable(); // 50% - nullable porque puede estar bloqueado
            $table->decimal('score_conducta', 5, 2)->default(0.00); // 10%
            $table->decimal('promedio_unidad', 5, 2)->nullable(); // Calculado automáticamente
            $table->boolean('derecho_examen')->default(true); // Si tiene derecho a examen
            $table->timestamps();
            
            // Índice único para evitar duplicados de unidad por inscripción
            $table->unique(['inscripcion_id', 'unidad']);
            // Índices para búsquedas
            $table->index('inscripcion_id');
            $table->index('unidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('calificaciones_detalle');
    }
};


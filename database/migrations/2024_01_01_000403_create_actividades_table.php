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
        Schema::create('actividades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_load_id')->constrained('academic_loads')->onDelete('cascade');
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->tinyInteger('unidad'); // 1, 2, 3
            $table->decimal('valor_maximo', 5, 2); // Puntos que vale la actividad
            $table->enum('categoria', ['TAREA', 'EXAMEN', 'CONDUCTA']);
            $table->date('fecha_limite')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
            
            // Índices
            $table->index('academic_load_id');
            $table->index('unidad');
            $table->index('categoria');
            $table->index(['academic_load_id', 'unidad', 'categoria']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actividades');
    }
};


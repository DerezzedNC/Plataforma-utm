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
        // Verificar si las tablas necesarias existen
        $hasUsers = Schema::hasTable('users');
        $hasAcademicLoads = Schema::hasTable('academic_loads');

        Schema::create('inscripciones', function (Blueprint $table) use ($hasUsers, $hasAcademicLoads) {
            $table->id();
            
            // Crear foreign key a users solo si existe
            if ($hasUsers) {
                $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('student_id');
            }
            
            // Crear foreign key a academic_loads solo si existe
            if ($hasAcademicLoads) {
                $table->foreignId('academic_load_id')->constrained('academic_loads')->onDelete('cascade');
            } else {
                $table->unsignedBigInteger('academic_load_id')->nullable();
            }
            
            $table->string('cuatrimestre'); // Formato: 2025-1
            $table->decimal('promedio_final', 5, 2)->nullable();
            $table->boolean('aprobado')->default(false);
            $table->timestamps();
            
            // Índice único para evitar duplicados
            $table->unique(['student_id', 'academic_load_id', 'cuatrimestre']);
            // Índices para búsquedas
            $table->index('student_id');
            $table->index('academic_load_id');
            $table->index('cuatrimestre');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('inscripciones');
    }
};


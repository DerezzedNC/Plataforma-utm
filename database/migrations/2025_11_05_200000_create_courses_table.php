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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
            $table->enum('tipo', ['interno', 'externo']);
            $table->string('nombre');
            $table->text('descripcion');
            $table->string('tiempo_duracion'); // Ej: "8 semanas", "40 horas"
            $table->decimal('costo', 10, 2)->nullable();
            $table->string('link')->nullable();
            $table->string('aula')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            
            // Índice para búsquedas por tipo
            $table->index('tipo');
            $table->index('activo');
        });

        // Tabla pivot para la relación many-to-many entre cursos y carreras
        Schema::create('course_career', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->onDelete('cascade');
            $table->foreignId('career_id')->constrained('careers')->onDelete('cascade');
            $table->timestamps();
            
            // Índice único para evitar duplicados
            $table->unique(['course_id', 'career_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_career');
        Schema::dropIfExists('courses');
    }
};


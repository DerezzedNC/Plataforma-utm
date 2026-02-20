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
        Schema::create('actividades_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actividad_id')->constrained('actividades')->onDelete('cascade');
            $table->foreignId('student_id')->constrained('users')->onDelete('cascade');
            $table->decimal('calificacion_obtenida', 5, 2)->default(0.00);
            $table->text('observaciones')->nullable();
            $table->timestamp('calificado_en')->nullable();
            $table->foreignId('calificado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();
            
            // Índice único para evitar duplicados
            $table->unique(['actividad_id', 'student_id']);
            // Índices para búsquedas
            $table->index('actividad_id');
            $table->index('student_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('actividades_entregas');
    }
};


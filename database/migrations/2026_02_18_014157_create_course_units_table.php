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
        Schema::create('course_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('academic_load_id')->constrained('academic_loads')->onDelete('cascade');
            $table->string('nombre'); // Ej: "Unidad 1", "Unidad 2"
            $table->integer('porcentaje'); // Valor porcentual (20, 30, 50, etc.)
            $table->timestamps();
            
            // Índice para búsquedas
            $table->index('academic_load_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_units');
    }
};

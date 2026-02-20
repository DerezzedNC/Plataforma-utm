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
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('codigo')->unique();
            $table->integer('grado'); // 1, 2, 3, 4, 5
            $table->string('carrera'); // Para filtrar por carrera
            $table->integer('creditos')->default(0);
            $table->text('descripcion')->nullable();
            $table->timestamps();
            
            // Índice para búsquedas por grado y carrera
            $table->index(['grado', 'carrera']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subjects');
    }
};

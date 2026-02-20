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
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained()->onDelete('cascade');
            $table->string('nombre');
            $table->string('codigo')->unique();
            $table->string('tipo'); // 'Aula', 'Laboratorio', 'Taller', etc.
            $table->integer('capacidad')->default(0);
            $table->text('equipamiento')->nullable(); // Para laboratorios
            $table->timestamps();
            
            // Índice para búsquedas por edificio y tipo
            $table->index(['building_id', 'tipo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};

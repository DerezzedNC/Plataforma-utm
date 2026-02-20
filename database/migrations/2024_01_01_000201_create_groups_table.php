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
        Schema::create('groups', function (Blueprint $table) {
            $table->id();
            $table->string('carrera');
            $table->integer('grado'); // 1, 2, 3, 4, 5
            $table->string('grupo'); // A, B, C, D, E
            $table->timestamps();
            
            // Índice único para evitar duplicados de carrera+grado+grupo
            $table->unique(['carrera', 'grado', 'grupo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('groups');
    }
};

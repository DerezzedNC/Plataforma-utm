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
        Schema::table('attendances', function (Blueprint $table) {
            // Agregar columna unidad (1, 2, 3)
            $table->tinyInteger('unidad')->default(1)->after('fecha');
            
            // Cambiar presente (boolean) a estado (enum)
            $table->dropColumn('presente');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->enum('estado', ['presente', 'falta', 'retardo', 'justificado'])->default('presente')->after('unidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropColumn('unidad');
            $table->dropColumn('estado');
            $table->boolean('presente')->default(true)->after('fecha');
        });
    }
};


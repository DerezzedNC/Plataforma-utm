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
        // Verificar que la tabla attendances existe antes de intentar modificarla
        if (!Schema::hasTable('attendances')) {
            return;
        }

        // Verificar si las columnas ya existen para evitar errores en re-ejecuciones
        $hasUnidad = Schema::hasColumn('attendances', 'unidad');
        $hasEstado = Schema::hasColumn('attendances', 'estado');
        $hasPresente = Schema::hasColumn('attendances', 'presente');

        // Agregar columna unidad si no existe
        if (!$hasUnidad) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->tinyInteger('unidad')->default(1)->after('fecha');
            });
        }

        // Eliminar columna presente solo si existe y si estado no existe aún
        if ($hasPresente && !$hasEstado) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->dropColumn('presente');
            });
        }

        // Agregar columna estado si no existe
        if (!$hasEstado) {
            Schema::table('attendances', function (Blueprint $table) {
                $table->enum('estado', ['presente', 'falta', 'retardo', 'justificado'])->default('presente')->after('unidad');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Verificar que la tabla attendances existe antes de intentar modificarla
        if (!Schema::hasTable('attendances')) {
            return;
        }

        Schema::table('attendances', function (Blueprint $table) {
            // Eliminar columnas solo si existen
            if (Schema::hasColumn('attendances', 'unidad')) {
                $table->dropColumn('unidad');
            }
            if (Schema::hasColumn('attendances', 'estado')) {
                $table->dropColumn('estado');
            }
            // Restaurar columna presente solo si no existe
            if (!Schema::hasColumn('attendances', 'presente')) {
                $table->boolean('presente')->default(true)->after('fecha');
            }
        });
    }
};


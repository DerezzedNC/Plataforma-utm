<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Para SQLite, necesitamos agregar la columna primero y luego la foreign key
        if (Schema::hasColumn('attendances', 'course_unit_id')) {
            return; // La columna ya existe, no hacer nada
        }
        
        // SQLite no soporta 'after()', así que agregamos la columna sin especificar posición
        if (DB::getDriverName() === 'sqlite') {
            DB::statement('ALTER TABLE attendances ADD COLUMN course_unit_id INTEGER NULL');
        } else {
            Schema::table('attendances', function (Blueprint $table) {
                // Agregar columna course_unit_id como nullable para permitir registros antiguos
                $table->unsignedBigInteger('course_unit_id')->nullable()->after('unidad');
            });
        }
        
        // Agregar índice
        Schema::table('attendances', function (Blueprint $table) {
            $table->index('course_unit_id');
        });
        
        // Agregar foreign key (SQLite requiere que esto se haga en un paso separado)
        if (DB::getDriverName() !== 'sqlite') {
            Schema::table('attendances', function (Blueprint $table) {
                $table->foreign('course_unit_id')->references('id')->on('course_units')->onDelete('cascade');
            });
        } else {
            // Para SQLite, usar DB::statement para agregar la foreign key
            // Nota: SQLite tiene limitaciones con ALTER TABLE para foreign keys
            // En este caso, solo creamos la columna y el índice
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            // Eliminar índice primero
            $table->dropIndex(['course_unit_id']);
            
            // Eliminar foreign key y columna
            $table->dropForeign(['course_unit_id']);
            $table->dropColumn('course_unit_id');
        });
    }
};

<?php

/**
 * Script para agregar la columna course_unit_id a la tabla attendances
 * Ejecutar con: php artisan tinker
 * Luego copiar y pegar el contenido de este archivo
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    // Verificar si la columna ya existe
    if (Schema::hasColumn('attendances', 'course_unit_id')) {
        echo "La columna course_unit_id ya existe.\n";
        exit;
    }
    
    // Agregar la columna usando SQL directo (SQLite compatible)
    DB::statement('ALTER TABLE attendances ADD COLUMN course_unit_id INTEGER NULL');
    
    // Crear índice
    try {
        DB::statement('CREATE INDEX IF NOT EXISTS attendances_course_unit_id_index ON attendances(course_unit_id)');
    } catch (\Exception $e) {
        echo "Advertencia: No se pudo crear el índice (puede que ya exista): " . $e->getMessage() . "\n";
    }
    
    echo "Columna course_unit_id agregada exitosamente.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

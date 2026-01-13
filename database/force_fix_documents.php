<?php

/**
 * Script para corregir la tabla documents manualmente
 * Ejecuta este script desde la línea de comandos:
 * php database/force_fix_documents.php
 */

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

try {
    echo "Iniciando corrección de la tabla documents...\n";
    
    // Verificar si la tabla existe
    if (!Schema::hasTable('documents')) {
        echo "La tabla documents no existe. Ejecuta las migraciones primero.\n";
        exit(1);
    }
    
    // Deshabilitar foreign keys temporalmente
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys=OFF');
    }
    
    // Crear tabla temporal
    echo "Creando tabla temporal...\n";
    DB::statement('CREATE TABLE IF NOT EXISTS documents_temp (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        student_id INTEGER NOT NULL,
        tipo_documento VARCHAR(255) NOT NULL,
        motivo TEXT,
        estado VARCHAR(255) NOT NULL DEFAULT "pendiente_revisar",
        solicitado_en TIMESTAMP NULL,
        listo_en TIMESTAMP NULL,
        entregado_en TIMESTAMP NULL,
        administrador_id INTEGER NULL,
        observaciones TEXT,
        created_at TIMESTAMP NULL,
        updated_at TIMESTAMP NULL
    )');
    
    // Copiar datos y actualizar estados
    echo "Copiando datos...\n";
    $count = DB::table('documents')->count();
    echo "Registros encontrados: $count\n";
    
    if ($count > 0) {
        DB::statement('INSERT INTO documents_temp 
            (id, student_id, tipo_documento, motivo, estado, solicitado_en, listo_en, entregado_en, administrador_id, observaciones, created_at, updated_at)
            SELECT 
                id, 
                student_id, 
                tipo_documento, 
                motivo,
                CASE 
                    WHEN estado = "solicitado" THEN "pendiente_revisar"
                    WHEN estado = "listo" THEN "listo_recoger"
                    WHEN estado = "entregado" THEN "finalizado"
                    ELSE estado
                END as estado,
                solicitado_en,
                listo_en,
                entregado_en,
                administrador_id,
                observaciones,
                created_at,
                updated_at
            FROM documents');
    }
    
    // Eliminar tabla antigua
    echo "Eliminando tabla antigua...\n";
    DB::statement('DROP TABLE IF EXISTS documents');
    
    // Renombrar tabla temporal
    echo "Renombrando tabla temporal...\n";
    DB::statement('ALTER TABLE documents_temp RENAME TO documents');
    
    // Re-habilitar foreign keys
    if (DB::getDriverName() === 'sqlite') {
        DB::statement('PRAGMA foreign_keys=ON');
    }
    
    echo "¡Corrección completada exitosamente!\n";
    echo "La tabla documents ahora acepta los nuevos estados.\n";
    
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}


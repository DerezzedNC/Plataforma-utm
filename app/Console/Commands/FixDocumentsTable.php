<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixDocumentsTable extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'documents:fix-table';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Corrige la tabla documents eliminando el CHECK constraint del ENUM';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Iniciando corrección de la tabla documents...');
        
        if (!Schema::hasTable('documents')) {
            $this->error('La tabla documents no existe. Ejecuta las migraciones primero.');
            return 1;
        }
        
        try {
            // Deshabilitar foreign keys temporalmente
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys=OFF');
            }
            
            // Crear tabla temporal
            $this->info('Creando tabla temporal...');
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
            $this->info('Copiando datos...');
            $count = DB::table('documents')->count();
            $this->info("Registros encontrados: $count");
            
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
            $this->info('Eliminando tabla antigua...');
            DB::statement('DROP TABLE IF EXISTS documents');
            
            // Renombrar tabla temporal
            $this->info('Renombrando tabla temporal...');
            DB::statement('ALTER TABLE documents_temp RENAME TO documents');
            
            // Re-habilitar foreign keys
            if (DB::getDriverName() === 'sqlite') {
                DB::statement('PRAGMA foreign_keys=ON');
            }
            
            $this->info('¡Corrección completada exitosamente!');
            $this->info('La tabla documents ahora acepta los nuevos estados.');
            
            return 0;
            
        } catch (\Exception $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}


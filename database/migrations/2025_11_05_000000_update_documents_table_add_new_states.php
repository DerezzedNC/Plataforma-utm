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
        // Para SQLite, necesitamos recrear la tabla ya que no soporta MODIFY COLUMN
        if (DB::getDriverName() === 'sqlite') {
            // Deshabilitar foreign keys temporalmente
            DB::statement('PRAGMA foreign_keys=OFF');
            
            // SQLite no soporta modificar ENUMs directamente, así que necesitamos recrear la tabla
            // Primero crear una tabla temporal sin el CHECK constraint
            DB::statement('CREATE TABLE documents_new (
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
            
            // Copiar datos y actualizar estados al mismo tiempo
            // Solo copiar si la tabla documents existe y tiene datos
            try {
                $count = DB::table('documents')->count();
                if ($count > 0) {
                    DB::statement('INSERT INTO documents_new 
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
            } catch (\Exception $e) {
                // Si hay error, continuar sin datos
            }
            
            // Eliminar tabla antigua
            DB::statement('DROP TABLE IF EXISTS documents');
            
            // Renombrar nueva tabla
            DB::statement('ALTER TABLE documents_new RENAME TO documents');
            
            // Re-habilitar foreign keys
            DB::statement('PRAGMA foreign_keys=ON');
        } else {
            // Para MySQL/MariaDB, primero cambiar el enum, luego actualizar estados
            DB::statement("ALTER TABLE documents MODIFY COLUMN estado ENUM('pendiente_revisar', 'pagar_documentos', 'en_proceso', 'listo_recoger', 'finalizado', 'cancelado') DEFAULT 'pendiente_revisar'");
            
            // Actualizar estados existentes
            DB::table('documents')->where('estado', 'solicitado')->update(['estado' => 'pendiente_revisar']);
            DB::table('documents')->where('estado', 'listo')->update(['estado' => 'listo_recoger']);
            DB::table('documents')->where('estado', 'entregado')->update(['estado' => 'finalizado']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir estados
        DB::table('documents')->where('estado', 'pendiente_revisar')->update(['estado' => 'solicitado']);
        DB::table('documents')->where('estado', 'listo_recoger')->update(['estado' => 'listo']);
        DB::table('documents')->where('estado', 'finalizado')->update(['estado' => 'entregado']);
        
        // Revertir el enum
        if (DB::getDriverName() === 'sqlite') {
            Schema::table('documents', function (Blueprint $table) {
                $table->string('estado')->default('solicitado')->change();
            });
        } else {
            DB::statement("ALTER TABLE documents MODIFY COLUMN estado ENUM('solicitado', 'en_proceso', 'listo', 'entregado', 'cancelado') DEFAULT 'solicitado'");
        }
    }
};


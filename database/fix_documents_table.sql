-- Script SQL para corregir la tabla documents en SQLite
-- Ejecuta esto manualmente si la migración no funciona

PRAGMA foreign_keys=OFF;

-- Crear tabla temporal sin CHECK constraint
CREATE TABLE documents_new (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    student_id INTEGER NOT NULL,
    tipo_documento VARCHAR(255) NOT NULL,
    motivo TEXT,
    estado VARCHAR(255) NOT NULL DEFAULT 'pendiente_revisar',
    solicitado_en TIMESTAMP NULL,
    listo_en TIMESTAMP NULL,
    entregado_en TIMESTAMP NULL,
    administrador_id INTEGER NULL,
    observaciones TEXT,
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);

-- Copiar datos y actualizar estados
INSERT INTO documents_new 
    (id, student_id, tipo_documento, motivo, estado, solicitado_en, listo_en, entregado_en, administrador_id, observaciones, created_at, updated_at)
SELECT 
    id, 
    student_id, 
    tipo_documento, 
    motivo,
    CASE 
        WHEN estado = 'solicitado' THEN 'pendiente_revisar'
        WHEN estado = 'listo' THEN 'listo_recoger'
        WHEN estado = 'entregado' THEN 'finalizado'
        ELSE estado
    END as estado,
    solicitado_en,
    listo_en,
    entregado_en,
    administrador_id,
    observaciones,
    created_at,
    updated_at
FROM documents;

-- Eliminar tabla antigua
DROP TABLE documents;

-- Renombrar nueva tabla
ALTER TABLE documents_new RENAME TO documents;

PRAGMA foreign_keys=ON;


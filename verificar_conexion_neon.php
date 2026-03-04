<?php
/**
 * Script para verificar conexión a Neon.tech
 * 
 * USO: php verificar_conexion_neon.php
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "🔍 Verificando conexión a Neon.tech...\n\n";

try {
    $pdo = DB::connection('pgsql')->getPdo();
    echo "✅ ¡Conexión exitosa!\n\n";
    
    // Obtener información de la base de datos
    $database = DB::connection('pgsql')->getDatabaseName();
    $host = config('database.connections.pgsql.host');
    $port = config('database.connections.pgsql.port');
    
    echo "📊 Información de conexión:\n";
    echo "   Host: {$host}\n";
    echo "   Port: {$port}\n";
    echo "   Database: {$database}\n";
    echo "   Driver: PostgreSQL\n\n";
    
    // Verificar si hay tablas
    $tables = DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public'");
    $tableCount = count($tables);
    
    echo "📋 Tablas existentes: {$tableCount}\n";
    if ($tableCount > 0) {
        echo "   (La base de datos ya tiene tablas)\n";
    } else {
        echo "   (La base de datos está vacía - lista para migraciones)\n";
    }
    
    echo "\n✅ Todo está listo para ejecutar las migraciones.\n";
    echo "   Ejecuta: php artisan migrate:fresh\n";
    
} catch (\Exception $e) {
    echo "❌ Error de conexión:\n";
    echo "   " . $e->getMessage() . "\n\n";
    echo "🔧 Verifica:\n";
    echo "   1. Que las credenciales en .env sean correctas\n";
    echo "   2. Que Neon.tech esté activo (no pausado)\n";
    echo "   3. Que el host y puerto sean correctos\n";
    exit(1);
}

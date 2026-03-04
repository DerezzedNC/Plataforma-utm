<?php
/**
 * Script de ayuda para migrar a nueva base de datos
 * 
 * USO:
 * php migrate_to_new_db.php
 * 
 * Este script te guiará paso a paso para migrar a una nueva BD
 */

echo "🔄 MIGRACIÓN A NUEVA BASE DE DATOS\n";
echo "===================================\n\n";

echo "📋 Situación actual:\n";
echo "   - Todas las migraciones están ejecutadas en SQLite\n";
echo "   - Necesitas migrar a una nueva base de datos\n\n";

echo "🎯 ¿Qué base de datos quieres usar?\n";
echo "   1. SQLite (Desarrollo local - Ya funciona)\n";
echo "   2. PostgreSQL/Supabase (Producción)\n";
echo "   3. MySQL/MariaDB\n";
echo "   4. Cancelar\n\n";

$opcion = readline("Elige una opción (1-4): ");

switch ($opcion) {
    case '1':
        echo "\n✅ SQLite - Configuración:\n";
        echo "   Edita tu .env y agrega:\n\n";
        echo "   DB_CONNECTION=sqlite\n";
        echo "   DB_DATABASE=database/database.sqlite\n\n";
        echo "   Luego ejecuta: php artisan migrate:fresh\n";
        break;
        
    case '2':
        echo "\n✅ PostgreSQL/Supabase - Configuración:\n";
        echo "   Edita tu .env y agrega:\n\n";
        echo "   DB_CONNECTION=pgsql\n";
        echo "   DB_HOST=aws-1-us-east-2.pooler.supabase.com\n";
        echo "   DB_PORT=5432\n";
        echo "   DB_DATABASE=postgres\n";
        echo "   DB_USERNAME=postgres.tvnnomkyccugspsjlluw\n";
        echo "   DB_PASSWORD=tu_password_aqui\n";
        echo "   DB_SSLMODE=require\n\n";
        echo "   ⚠️ IMPORTANTE: Verifica que el puerto sea 5432 para Session Pooler\n";
        echo "   Luego ejecuta: php artisan migrate:fresh\n";
        break;
        
    case '3':
        echo "\n✅ MySQL/MariaDB - Configuración:\n";
        echo "   Edita tu .env y agrega:\n\n";
        echo "   DB_CONNECTION=mysql\n";
        echo "   DB_HOST=127.0.0.1\n";
        echo "   DB_PORT=3306\n";
        echo "   DB_DATABASE=utm_web\n";
        echo "   DB_USERNAME=root\n";
        echo "   DB_PASSWORD=tu_password\n\n";
        echo "   Luego ejecuta: php artisan migrate:fresh\n";
        break;
        
    case '4':
        echo "\n❌ Operación cancelada.\n";
        exit(0);
        
    default:
        echo "\n❌ Opción inválida.\n";
        exit(1);
}

echo "\n📝 Pasos siguientes:\n";
echo "   1. Edita tu archivo .env con la configuración de arriba\n";
echo "   2. Guarda el archivo\n";
echo "   3. Ejecuta: php artisan migrate:fresh\n";
echo "   4. Verifica: php artisan migrate:status\n\n";

echo "✅ ¡Listo! Sigue los pasos de arriba.\n";

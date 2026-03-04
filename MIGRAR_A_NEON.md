# 🚀 Migración de SQLite a Neon.tech (PostgreSQL)

## 📋 Pasos para Migrar

### Paso 1: Obtener Credenciales de Neon.tech

1. Ve a tu proyecto en [Neon.tech](https://neon.tech)
2. Ve a **Dashboard** → Tu proyecto → **Connection Details**
3. Copia la **Connection String** o los datos individuales:
   - **Host**: `ep-xxxx-xxxx.us-east-2.aws.neon.tech`
   - **Database**: `neondb` (o el nombre que hayas puesto)
   - **User**: `neondb_owner` (o similar)
   - **Password**: (la contraseña que te dieron)
   - **Port**: `5432` (o el que te indiquen)

---

### Paso 2: Configurar el archivo .env

Abre tu archivo `.env` y reemplaza las líneas de base de datos con:

```env
DB_CONNECTION=pgsql
DB_HOST=ep-xxxx-xxxx.us-east-2.aws.neon.tech
DB_PORT=5432
DB_DATABASE=neondb
DB_USERNAME=neondb_owner
DB_PASSWORD=tu_password_de_neon
DB_SSLMODE=require
```

**⚠️ IMPORTANTE:**
- Reemplaza `ep-xxxx-xxxx.us-east-2.aws.neon.tech` con tu host real de Neon
- Reemplaza `neondb` con el nombre de tu base de datos
- Reemplaza `neondb_owner` con tu usuario real
- Reemplaza `tu_password_de_neon` con tu contraseña real

---

### Paso 3: Verificar la Conexión

Antes de migrar, verifica que la conexión funcione:

```bash
php artisan tinker
```

Dentro de tinker, ejecuta:
```php
DB::connection()->getPdo();
```

Si no da error, la conexión está bien. Escribe `exit` para salir.

---

### Paso 4: Ejecutar las Migraciones

**⚠️ ADVERTENCIA:** Esto borrará todas las tablas existentes en Neon y las recreará desde cero.

```bash
php artisan migrate:fresh
```

Este comando:
- ✅ Borra todas las tablas existentes
- ✅ Ejecuta todas las migraciones desde cero
- ✅ Crea todas las tablas con la estructura correcta

---

### Paso 5: Verificar que Todo Funcione

```bash
# Ver estado de migraciones
php artisan migrate:status

# Ver tablas creadas
php artisan db:show
```

---

## 🆘 Solución de Problemas

### Error: "Connection refused"
- Verifica que el host, puerto, usuario y contraseña sean correctos
- Verifica que Neon.tech esté activo (no pausado)

### Error: "SSL connection required"
- Asegúrate de tener `DB_SSLMODE=require` en tu `.env`

### Error: "Table already exists"
- Ejecuta: `php artisan migrate:fresh` para borrar y recrear

### Error: "Migration table not found"
- Ejecuta: `php artisan migrate:install` y luego `php artisan migrate`

---

## 📝 Resumen Rápido

1. ✅ Obtén credenciales de Neon.tech
2. ✅ Configura `.env` con `DB_CONNECTION=pgsql` y tus credenciales
3. ✅ Verifica conexión: `php artisan tinker` → `DB::connection()->getPdo();`
4. ✅ Ejecuta migraciones: `php artisan migrate:fresh`
5. ✅ Verifica: `php artisan migrate:status`

---

## 💡 Nota sobre Datos

**⚠️ IMPORTANTE:** `migrate:fresh` borra TODOS los datos. Si tienes datos importantes en SQLite que quieres conservar, necesitarás:

1. Exportar datos de SQLite
2. Importar a Neon.tech manualmente

Si solo necesitas la estructura (tablas vacías), `migrate:fresh` es perfecto.

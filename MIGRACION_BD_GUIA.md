# 🔄 Guía para Migrar a Nueva Base de Datos

## 📋 Situación Actual
- ✅ Todas las migraciones están ejecutadas en SQLite
- ⚠️ Cambiaste a PostgreSQL pero no funcionó
- 🎯 Necesitas migrar a una nueva base de datos

---

## 🎯 Paso 1: Decidir qué Base de Datos Usar

### Opción A: SQLite (Desarrollo Local) ⚡
**Ventajas:**
- ✅ Ya funciona perfectamente
- ✅ No requiere servidor
- ✅ Perfecto para desarrollo
- ✅ Archivo único: `database/database.sqlite`

**Configuración:**
```env
DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite
```

---

### Opción B: PostgreSQL (Producción - Supabase) 🚀
**Ventajas:**
- ✅ Base de datos en la nube
- ✅ Escalable
- ✅ Perfecto para producción

**⚠️ IMPORTANTE: El problema anterior era el PUERTO**

**Configuración CORRECTA para Supabase Session Pooler:**
```env
DB_CONNECTION=pgsql
DB_HOST=aws-1-us-east-2.pooler.supabase.com
DB_PORT=5432  # ← Este es el correcto para Session Pooler
DB_DATABASE=postgres
DB_USERNAME=postgres.tvnnomkyccugspsjlluw
DB_PASSWORD=tu_password_aqui
DB_SSLMODE=require
```

**O si usas Direct Connection:**
```env
DB_CONNECTION=pgsql
DB_HOST=aws-1-us-east-2.aws.supabase.io  # ← Host diferente
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres
DB_PASSWORD=tu_password_aqui
DB_SSLMODE=require
```

---

### Opción C: MySQL/MariaDB (Alternativa) 💾
**Ventajas:**
- ✅ Muy común
- ✅ Buena compatibilidad

**Configuración:**
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=utm_web
DB_USERNAME=root
DB_PASSWORD=tu_password
```

---

## 🔧 Paso 2: Configurar el .env

1. Abre tu archivo `.env`
2. Reemplaza las líneas de `DB_*` con la configuración de tu opción elegida
3. Guarda el archivo

---

## 🗑️ Paso 3: Limpiar Migraciones (Solo si cambias de BD)

Si cambias de base de datos (ej: de SQLite a PostgreSQL), necesitas limpiar:

```bash
# Opción 1: Fresh (Borra TODO y recrea)
php artisan migrate:fresh

# Opción 2: Reset (Borra todo pero mantiene estructura)
php artisan migrate:reset
php artisan migrate

# Opción 3: Solo ejecutar migraciones (si la BD está vacía)
php artisan migrate
```

---

## ✅ Paso 4: Ejecutar Migraciones

```bash
# Verificar estado
php artisan migrate:status

# Ejecutar migraciones
php artisan migrate

# Si hay errores, ver en modo "pretend" (solo muestra SQL sin ejecutar)
php artisan migrate --pretend
```

---

## 🧪 Paso 5: Verificar que Funcione

```bash
# Probar conexión
php artisan tinker
>>> DB::connection()->getPdo();
>>> exit

# Verificar tablas creadas
php artisan db:show
```

---

## 🆘 Si Algo Sale Mal

### Error: "Table already exists"
```bash
php artisan migrate:fresh
```

### Error: "Connection refused"
- Verifica que el servidor de BD esté corriendo
- Verifica host, puerto, usuario y contraseña

### Error: "Migration table not found"
```bash
php artisan migrate:install
php artisan migrate
```

---

## 📝 Resumen Rápido

1. **Elige tu BD** (SQLite, PostgreSQL, MySQL)
2. **Configura `.env`** con las credenciales correctas
3. **Ejecuta:** `php artisan migrate:fresh` (si cambias de BD)
4. **Verifica:** `php artisan migrate:status`

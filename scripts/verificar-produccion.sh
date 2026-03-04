#!/bin/bash

# Script de verificación pre-deploy para producción
# Uso: ./scripts/verificar-produccion.sh

echo "🔍 Verificando configuración antes de deploy..."
echo ""

# Colores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

ERRORS=0

# Verificar que estamos en el directorio correcto
if [ ! -f "artisan" ]; then
    echo -e "${RED}❌ Error: No se encontró artisan. Ejecuta este script desde la raíz del proyecto.${NC}"
    exit 1
fi

echo "📦 Verificando dependencias..."
if [ ! -d "vendor" ]; then
    echo -e "${RED}❌ Error: vendor/ no existe. Ejecuta: composer install${NC}"
    ERRORS=$((ERRORS + 1))
else
    echo -e "${GREEN}✅ Dependencias instaladas${NC}"
fi

echo ""
echo "🔧 Verificando configuración..."
php artisan config:clear
php artisan cache:clear

echo ""
echo "🗄️  Verificando base de datos..."
php artisan migrate:status > /dev/null 2>&1
if [ $? -eq 0 ]; then
    echo -e "${GREEN}✅ Conexión a base de datos OK${NC}"
else
    echo -e "${RED}❌ Error de conexión a base de datos${NC}"
    ERRORS=$((ERRORS + 1))
fi

echo ""
echo "📁 Verificando permisos y directorios..."
php artisan production:verify
VERIFY_EXIT=$?

if [ $VERIFY_EXIT -ne 0 ]; then
    ERRORS=$((ERRORS + 1))
fi

echo ""
echo "🔗 Verificando link de storage..."
if [ ! -L "public/storage" ] && [ ! -d "public/storage" ]; then
    echo -e "${YELLOW}⚠️  Link de storage no existe. Creando...${NC}"
    php artisan storage:link
fi

echo ""
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}✅ ¡Todo listo para deploy!${NC}"
    exit 0
else
    echo -e "${RED}❌ Se encontraron $ERRORS error(es). Corrígelos antes de hacer deploy.${NC}"
    exit 1
fi

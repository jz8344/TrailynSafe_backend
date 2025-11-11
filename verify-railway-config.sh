#!/bin/bash
# Script de verificación pre-deployment para Railway

echo "🔍 Verificando configuración para Railway..."
echo ""

# Colores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

errors=0
warnings=0

# Verificar archivos requeridos
echo "📁 Verificando archivos requeridos..."
files=("Procfile" "nixpacks.toml" ".env.example" "composer.json" "package.json")
for file in "${files[@]}"; do
    if [ -f "$file" ]; then
        echo -e "${GREEN}✓${NC} $file encontrado"
    else
        echo -e "${RED}✗${NC} $file NO encontrado"
        ((errors++))
    fi
done
echo ""

# Verificar composer.json
echo "📦 Verificando composer.json..."
if grep -q "mongodb/laravel-mongodb" composer.json; then
    echo -e "${GREEN}✓${NC} MongoDB driver configurado"
else
    echo -e "${RED}✗${NC} MongoDB driver NO encontrado en composer.json"
    ((errors++))
fi

if grep -q "laravel/sanctum" composer.json; then
    echo -e "${GREEN}✓${NC} Laravel Sanctum configurado"
else
    echo -e "${YELLOW}⚠${NC} Laravel Sanctum no encontrado (puede ser opcional)"
    ((warnings++))
fi
echo ""

# Verificar directorios de storage
echo "📂 Verificando directorios de storage..."
dirs=("storage/framework/cache" "storage/framework/sessions" "storage/framework/views" "storage/logs" "bootstrap/cache")
for dir in "${dirs[@]}"; do
    if [ -d "$dir" ]; then
        echo -e "${GREEN}✓${NC} $dir existe"
    else
        echo -e "${YELLOW}⚠${NC} $dir no existe (se creará automáticamente)"
        ((warnings++))
    fi
done
echo ""

# Verificar .env.example
echo "⚙️  Verificando .env.example..."
required_vars=("APP_KEY" "DB_CONNECTION" "MONGO_DSN" "MAIL_MAILER")
for var in "${required_vars[@]}"; do
    if grep -q "$var" .env.example; then
        echo -e "${GREEN}✓${NC} $var definido en .env.example"
    else
        echo -e "${RED}✗${NC} $var NO encontrado en .env.example"
        ((errors++))
    fi
done
echo ""

# Verificar migraciones
echo "🗄️  Verificando migraciones..."
migration_count=$(ls -1 database/migrations/*.php 2>/dev/null | wc -l)
if [ $migration_count -gt 0 ]; then
    echo -e "${GREEN}✓${NC} $migration_count archivos de migración encontrados"
else
    echo -e "${YELLOW}⚠${NC} No se encontraron migraciones"
    ((warnings++))
fi
echo ""

# Resumen
echo "════════════════════════════════════════"
if [ $errors -eq 0 ] && [ $warnings -eq 0 ]; then
    echo -e "${GREEN}✓ ¡Todo listo para deployment en Railway!${NC}"
    exit 0
elif [ $errors -eq 0 ]; then
    echo -e "${YELLOW}⚠ Listo con $warnings advertencias${NC}"
    echo "Puedes continuar con el deployment, pero revisa las advertencias."
    exit 0
else
    echo -e "${RED}✗ Se encontraron $errors errores y $warnings advertencias${NC}"
    echo "Por favor, corrige los errores antes de deployar."
    exit 1
fi

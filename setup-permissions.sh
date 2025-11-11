#!/bin/bash
# Script para preparar permisos en Railway

# Crear directorios necesarios si no existen
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Establecer permisos
chmod -R 775 storage
chmod -R 775 bootstrap/cache

echo "Permisos configurados correctamente"

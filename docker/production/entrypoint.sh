#!/bin/sh
set -e

cd /var/www/html

echo "⚙️ Preparando aplicación Laravel..."

# Nota: El volumen public se inicializa automáticamente la primera vez
# Si está vacío, los archivos se generarán cuando Laravel los necesite
# Para inicializar manualmente, ejecuta:
# docker run --rm -v selena-public:/dest devmartin01/selena:latest sh -c "cp -r /var/www/html/public/* /dest/"

# Crear symlink de storage si no existe
rm -rf public/storage
php artisan storage:link || true

# Limpiar cachés anteriores
php artisan optimize:clear

# Cachear todo lo necesario
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Optimizar Filament
php artisan filament:optimize || true

# Migraciones forzadas
php artisan migrate --force

# Crear super admin si tienes comando
php artisan app:setup-super-admin || true

# Crear directorios necesarios si no existen
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/views
mkdir -p storage/logs
mkdir -p bootstrap/cache

# Asegurar permisos en storage/app (bind mount desacoplado)
# Esto asegura que el bind mount ./selena-storage tenga permisos correctos
chown -R www-data:www-data /var/www/html/storage/app
chmod -R 775 /var/www/html/storage/app

# Asegurar permisos en storage/framework y storage/logs (temporales, no en bind mount)
chown -R www-data:www-data storage/framework storage/logs bootstrap/cache
chmod -R 775 storage/framework storage/logs bootstrap/cache

echo "✅ Laravel optimizado. Arrancando FPM..."
exec "$@"
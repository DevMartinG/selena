#!/bin/sh
set -e

cd /var/www/html

echo "⚙️ Preparando aplicación Laravel..."

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

# Asegurar permisos
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "✅ Laravel optimizado. Arrancando FPM..."
exec "$@"
#!/bin/bash
# Script para inicializar el volumen public la primera vez
# Uso: ./scripts/init-public-volume.sh

echo "📦 Inicializando volumen public (primera vez)..."

# Verificar que el volumen existe
if ! docker volume inspect selena-public >/dev/null 2>&1; then
    echo "⚠️  El volumen selena-public no existe. Se creará automáticamente al levantar los contenedores."
    exit 0
fi

# Copiar contenido de public/ desde la imagen al volumen
echo "Copiando archivos de public/ al volumen..."
docker run --rm \
    -v selena-public:/dest \
    devmartin01/selena:latest \
    sh -c "cp -r /var/www/html/public/* /dest/ 2>/dev/null && echo '✅ Archivos copiados correctamente' || echo '⚠️  Algunos archivos no se pudieron copiar'"

echo "✅ Inicialización completada"
echo ""
echo "💡 Ahora puedes levantar los contenedores:"
echo "   docker-compose -f docker-compose.prod.yml up -d"


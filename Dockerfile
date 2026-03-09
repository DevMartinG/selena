# ─────────────────────────────────────────────
# 🟩 Etapa 1: Build de assets con Node.js
# ─────────────────────────────────────────────
FROM node:20-alpine AS node-build

WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
COPY resources/ resources/

RUN npm ci && npm run build


# ─────────────────────────────────────────────
# 🟨 Etapa 2: Instalación PHP y dependencias
# ─────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS php-build
RUN apk add --no-cache \
    bash \
    git \
    curl \
    libzip-dev \
    libpng-dev \
    libxml2-dev \
    icu-dev \
    oniguruma-dev \
    unzip \
    zlib-dev \
    libjpeg-turbo-dev \
    libwebp-dev \
    freetype-dev

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp && \
    docker-php-ext-install -j$(nproc) pdo pdo_mysql mbstring zip exif bcmath gd intl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ✅ Copiar composer.json ANTES de instalar
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-autoloader

# ✅ Copiar el resto del código y generar autoload final
COPY . .
RUN composer dump-autoload --optimize --no-scripts


# ─────────────────────────────────────────────
# 🟥 Etapa 3: Imagen final limpia
# ─────────────────────────────────────────────
FROM php:8.3-fpm-alpine

# 🛠️ Añadimos las dependencias necesarias para que las extensiones funcionen
RUN apk add --no-cache \
    libzip \
    libpng \
    libxml2 \
    icu \
    oniguruma \
    zlib \
    libjpeg-turbo \
    libwebp \
    freetype

WORKDIR /var/www/html

# ✅ Copia de las extensiones y binarios de PHP
COPY --from=php-build /usr/local /usr/local

# ✅ Copia solo del vendor (ya optimizado en build)
COPY --from=php-build /var/www/html/vendor/ ./vendor
COPY . .

COPY --from=node-build /app/public/build ./public/build

RUN mkdir -p storage bootstrap/cache && \
    chmod -R 775 storage bootstrap/cache && \
    chown -R www-data:www-data storage bootstrap/cache

COPY docker/production/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

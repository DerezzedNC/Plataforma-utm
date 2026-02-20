FROM php:8.2-cli

# Instalar herramientas del sistema y extensiones para PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql

# Traer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configurar la carpeta de trabajo
WORKDIR /app
COPY . .

# Instalar las dependencias de Laravel
RUN composer install --optimize-autoloader --no-dev

# Dar permisos a las carpetas de almacenamiento
RUN chown -R www-data:www-data /app/storage /app/bootstrap/cache

# Iniciar el servidor conectando el puerto de Render
CMD php artisan serve --host=0.0.0.0 --port=${PORT:-10000}
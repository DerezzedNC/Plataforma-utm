# 1. Usar la imagen oficial de PHP con Apache
FROM php:8.2-apache

# 2. Instalar herramientas y traductor de PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql

# 3. Traer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Configurar la carpeta de trabajo
WORKDIR /var/www/html
COPY . .

# 5. Instalar dependencias de Laravel
RUN composer install --optimize-autoloader --no-dev

# 6. Dar permisos para que Laravel pueda guardar archivos
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 7. Configurar Apache para que apunte a la carpeta "public" de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 8. Activar las rutas amigables
RUN a2enmod rewrite

# 9. Abrir el puerto 80 (el estándar de internet)
EXPOSE 80
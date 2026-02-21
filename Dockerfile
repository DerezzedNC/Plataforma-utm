# 1. Usar la imagen oficial de PHP con Apache
FROM php:8.2-apache

# 2. Instalar herramientas, traductor de PostgreSQL y Node.js para compilar Vue
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    curl \
    && curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && docker-php-ext-install pdo pdo_pgsql

# 3. Traer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. Configurar la carpeta de trabajo
WORKDIR /var/www/html
COPY . .

# 5. Instalar dependencias de Laravel
RUN composer install --optimize-autoloader --no-dev

# 6. Compilar el frontend (Vue + Vite)
RUN npm install --legacy-peer-deps
RUN npm run build

# 7. Dar permisos para que Laravel pueda guardar archivos
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 8. Configurar Apache para que apunte a la carpeta "public" de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 9. Configurar Apache para que use el puerto dinámico de Render
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# 10. Activar las rutas amigables
RUN a2enmod rewrite

# 11. Destruir cualquier caché local atrapada (¡La solución!)
RUN php artisan config:clear
RUN php artisan cache:clear
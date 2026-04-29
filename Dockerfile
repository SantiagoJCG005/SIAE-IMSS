# Dockerfile
# Construye la imagen de la aplicacion SIAE-IMSS
# Usa PHP 8.2 con Apache incluido como base

FROM php:8.2-apache

# Instala la extension PDO MySQL requerida para conectar a la base de datos
RUN docker-php-ext-install pdo_mysql

# Habilita mod_rewrite (necesario para .htaccess) y mod_alias (para el Alias de URL)
RUN a2enmod rewrite alias

# Crea los directorios de uploads y exports con permisos correctos para Apache
RUN mkdir -p /var/www/html/uploads /var/www/html/exports \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/exports

# Copia la configuracion personalizada de PHP al contenedor
COPY docker/php/php.ini /usr/local/etc/php/conf.d/siae.ini

# Copia la configuracion de Apache al contenedor
COPY docker/apache/siae.conf /etc/apache2/sites-available/000-default.conf

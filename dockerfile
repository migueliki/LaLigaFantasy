FROM php:8.2-apache

# Instalar extensión mysqli
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Copiar todo el proyecto
COPY . /var/www/html/

# Cambiar el DocumentRoot a la carpeta app
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/app|g' /etc/apache2/sites-available/000-default.conf && \
    sed -i 's|<Directory /var/www/html>|<Directory /var/www/html/app>|g' /etc/apache2/sites-available/000-default.conf

# Habilitar mod_rewrite
RUN a2enmod rewrite

# Dar permisos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
FROM php:8.2-apache

# Install ekstensi PHP untuk MySQL/MariaDB
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Mengaktifkan mod_rewrite (untuk URL rewriting)
RUN a2enmod rewrite

# Enable environment variables
RUN echo "variables_order = \"EGPCS\"" >> /usr/local/etc/php/conf.d/docker-php-env.ini

RUN echo "ServerName antiabuse.local" >> /etc/apache2/apache2.conf

# Create symlink for assets if not mounted (backup solution)
RUN mkdir -p /var/www/html/assets || true

# Create uploads directory with proper permissions
RUN mkdir -p /var/www/uploads/reports && \
    chown -R www-data:www-data /var/www/uploads && \
    chmod -R 755 /var/www/uploads

# DocumentRoot bawaan sudah /var/www/html
WORKDIR /var/www/html


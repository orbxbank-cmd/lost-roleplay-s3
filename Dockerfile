FROM php:8.1-apache

# Enable Apache modules
RUN a2enmod rewrite

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_mysql

# Allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy project files
COPY . /var/www/html/

# Set permissions
RUN mkdir -p /var/www/html/uploads/proofs /var/www/html/public/assets/images/products && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html && \
    chmod -R 777 /var/www/html/uploads /var/www/html/public/assets/images/products

# Set PHP timezone & upload limits
RUN echo "date.timezone = Africa/Casablanca" > /usr/local/etc/php/conf.d/timezone.ini
RUN echo "upload_max_filesize = 10M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 12M" >> /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 80

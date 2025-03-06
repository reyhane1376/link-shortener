FROM docker.arvancloud.ir/php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev \
    libgmp-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install gmp \
    && docker-php-ext-install pdo_mysql zip

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy the application code
COPY . /var/www/html/

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install dependencies
RUN composer install --no-interaction --no-plugins --no-scripts

# Set the proper permissions
RUN chown -R www-data:www-data /var/www/html
RUN chmod -R 755 /var/www/html

# Expose port 80
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]

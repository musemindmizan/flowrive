# PHP 8.4 Apache Image
FROM php:8.4-apache

# Install System Dependencies & Extensions
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nodejs \
    npm

RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Enable Apache Mod Rewrite
RUN a2enmod rewrite

# Set Working Directory
WORKDIR /var/www/html

# Copy Project Files
COPY . .

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader

# Install Node & Build Assets
RUN npm install
RUN npm run build

# Change Apache Root Directory to /public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 'sub!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 'sub!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/conf-available/*.conf

# Set File Permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
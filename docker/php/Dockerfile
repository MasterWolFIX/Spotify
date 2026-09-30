FROM php:8.2-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_mysql \
    mbstring \
    intl \
    zip \
    opcache

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/app

# Copy composer files first (layer caching)
COPY composer.json composer.lock* ./

# Install PHP dependencies (ignore errors if lock file is missing)
RUN composer install --no-scripts --no-autoloader --prefer-dist --no-interaction || true

# Copy application source
COPY . .

# Generate optimized autoloader
RUN composer dump-autoload --optimize || true

# Set permissions
RUN chown -R www-data:www-data /var/www/app/var 2>/dev/null || mkdir -p /var/www/app/var && \
    chmod -R 775 /var/www/app/var

# PHP configuration for uploads
RUN cp /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini && \
    echo "upload_max_filesize=256M" >> /usr/local/etc/php/php.ini && \
    echo "post_max_size=256M" >> /usr/local/etc/php/php.ini && \
    echo "memory_limit=512M" >> /usr/local/etc/php/php.ini && \
    echo "max_execution_time=120" >> /usr/local/etc/php/php.ini && \
    echo "opcache.enable=1" >> /usr/local/etc/php/php.ini && \
    echo "opcache.memory_consumption=256" >> /usr/local/etc/php/php.ini && \
    echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/php.ini && \
    echo "opcache.revalidate_freq=0" >> /usr/local/etc/php/php.ini

# Entrypoint script
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN tr -d '\r' < /usr/local/bin/entrypoint.sh > /usr/local/bin/entrypoint_unix.sh && \
    mv /usr/local/bin/entrypoint_unix.sh /usr/local/bin/entrypoint.sh && \
    chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

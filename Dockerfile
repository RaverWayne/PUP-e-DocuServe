FROM php:8.3-cli

# Install system libraries needed to compile PHP extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libpng-dev \
    libjpeg-dev \
    libzip-dev \
    pkg-config \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PDO MySQL + curl + zip extensions (zip needed by PhpSpreadsheet)
RUN docker-php-ext-install pdo_mysql mysqli
RUN docker-php-ext-install curl
RUN docker-php-ext-install zip

# gd (needed by PhpSpreadsheet for image rendering)
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy application files
COPY . /app

# Install PHP dependencies (no dev, optimized autoloader)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# Expose port
EXPOSE 8080

# Start PHP built-in server
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t ."]

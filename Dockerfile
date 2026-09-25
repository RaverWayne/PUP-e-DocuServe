FROM php:8.3-cli

# Install system libraries needed to compile PHP extensions
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    pkg-config \
    && rm -rf /var/lib/apt/lists/*

# Install PDO MySQL + curl extensions
RUN docker-php-ext-install pdo_mysql mysqli
RUN docker-php-ext-install curl

# Set working directory
WORKDIR /app

# Copy application files
COPY . /app

# Expose port
EXPOSE 8080

# Start PHP built-in server
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-8080} -t ."]
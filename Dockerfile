FROM php:8.3-cli

RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libpng-dev \
    libjpeg-dev \
    libzip-dev \
    pkg-config \
    unzip \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install pdo_mysql mysqli
RUN docker-php-ext-install curl
RUN docker-php-ext-install zip

RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
...

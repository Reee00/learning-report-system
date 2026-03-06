FROM php:8.3-cli

# Install system dependencies + oniguruma untuk mbstring
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libzip-dev \
    libxml2-dev \
    libonig-dev \
    zip \
    unzip \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        zip \
        gd \
        xml \
        fileinfo \
        tokenizer \
        opcache \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

# Copy composer files dulu (cache layer)
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install \
    --optimize-autoloader \
    --no-dev \
    --no-interaction \
    --no-scripts \
    --ignore-platform-reqs

# Copy semua file aplikasi
COPY . .

# Post install
RUN composer run-script post-autoload-dump --no-interaction 2>/dev/null || true

# Set permissions storage & cache
RUN mkdir -p storage/logs \
             storage/framework/cache \
             storage/framework/sessions \
             storage/framework/views \
             bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Cache Laravel
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan storage:link || true

EXPOSE 8080

CMD php artisan serve --host=0.0.0.0 --port=8080
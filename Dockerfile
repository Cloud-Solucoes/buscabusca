FROM php:8.3-cli

# Extensões necessárias (SQLite + mbstring)
RUN apt-get update && apt-get install -y \
        libsqlite3-dev \
        libonig-dev \
    && docker-php-ext-install pdo_sqlite mbstring \
    && rm -rf /var/lib/apt/lists/*

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "-S", "0.0.0.0:8000", "-t", "public"]

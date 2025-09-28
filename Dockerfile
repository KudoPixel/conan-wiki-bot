# Use a lean base image for PHP-FPM on Alpine Linux
FROM php:8.3-fpm-alpine

# Core Setup: Install necessary system packages for PHP and extensions
# NOTE: mysql-client and mariadb-dev are required for pdo_mysql extension
# NOTE: zlib-dev is required for the zip extension
# NOTE: icu-dev is required for the intl extension
RUN apk update && apk add --no-cache \
    git \
    curl \
    libxml2-dev \
    libzip-dev \
    # Add missing system dependencies for extensions
    zlib-dev \
    mariadb-dev \
    mysql-client \
    icu-dev 

# Install PHP extensions
# json: Essential for JSON file handling and API payloads
# pdo_mysql: Installed for potential future database use
# zip: Necessary for Composer dependency management
# intl: Often required by Monolog and general localization/formatting
# curl: Essential for Guzzle and handling API communications (like Gemini)
RUN docker-php-ext-install \
    json \
    pdo_mysql \
    zip \
    intl \
    curl

# Install Composer (PHP Dependency Manager)
# Copying it from the official Composer image for simplicity and latest version
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set the main application working directory
WORKDIR /app

# Copy project files
# Copy Composer files first to leverage Docker layer caching
COPY composer.json composer.lock ./
# Install dependencies (skipping dev dependencies and optimizing autoloader)
RUN composer install --no-dev --optimize-autoloader

# Copy the rest of the application source code
COPY . /app

# Set correct permissions for the web server user (www-data)
# Important for cloud environments (like Render) to ensure log/config files are writable
RUN chown -R www-data:www-data /app

# Define the entry point: Start the PHP-FPM process
# FPM listens for requests from a web server (like Nginx)
CMD ["php-fpm"]

# Expose the standard FPM port
# The external web service will connect to this port
EXPOSE 9000

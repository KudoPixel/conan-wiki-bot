# =================================================================
# Detective Conan Bot - Dockerfile (Based on Debian/Apache - Optimized)
# =================================================================

# --- Base Stage: Use a stable PHP image with Apache (Debian) ---
# Using PHP 8.3 for best performance and support.
FROM php:8.3-apache

# --- 1. System Dependencies & PHP Extensions ---
# Install system libraries and PHP extensions in a single layer for efficiency.
# We removed 'json' because it's already included in PHP 8.3!
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    libicu-dev \
    libcurl4-openssl-dev \
    # Install required PHP extensions
    && docker-php-ext-install \
    zip \
    intl \
    curl \
    # Clean up cached files to keep the image small
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Enable Apache rewrite module (essential for routing)
RUN a2enmod rewrite

# --- 2. Apache Configuration for Webhook ---
# We copy a custom vhost configuration tailored to send all traffic
# to our single entry point: webhook.php
# NOTE: This assumes you place the vhost.conf file in a .docker/ directory.
COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

# --- 3. Composer Installation ---
# Install Composer, the PHP dependency manager.
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# --- 4. Application Build ---
# Set the working directory (standard Apache root).
WORKDIR /var/www/html

# Copy composer files first to leverage Docker's layer caching.
COPY composer.json composer.lock ./
# Install dependencies (skipping dev dependencies and optimizing autoloader)
RUN composer install --no-interaction --no-dev --optimize-autoloader

# Now, copy the rest of the application code.
COPY . .

# --- 5. Final Touches: Permissions ---
# Ensure the web server user owns the files.
RUN chown -R www-data:www-data /var/www/html

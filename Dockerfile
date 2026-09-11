# syntax=docker/dockerfile:1

# ==============================================================================
# Stage 1: Build Frontend Assets (Inertia Vue 3 + Tailwind CSS)
# ==============================================================================
FROM node:22-alpine AS frontend-builder

WORKDIR /app

# Copy package descriptors
COPY package*.json ./

# Install npm dependencies
RUN npm ci --no-audit

# Copy frontend source files and configuration
COPY vite.config.js ./
COPY resources ./resources
COPY public ./public

# Build production assets into public/build
RUN npm run build

# ==============================================================================
# Stage 2: PHP Application Runtime (Laravel 13 + REST API + Queue Worker)
# ==============================================================================
FROM php:8.4-cli-bookworm

WORKDIR /app

ENV DEBIAN_FRONTEND=noninteractive

# Install system dependencies & PHP extensions
RUN apt-get update && apt-get install -y --no-install-recommends \
    git \
    curl \
    unzip \
    libsqlite3-dev \
    libzip-dev \
    && docker-php-ext-install -j$(nproc) \
        pdo_sqlite \
        bcmath \
        pcntl \
        posix \
        zip \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy composer files and patches for dependency installation
COPY composer.json composer.lock ./
COPY patches ./patches

# Install PHP dependencies without dev dependencies
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copy full application code
COPY . .

# Copy compiled frontend assets from Stage 1
COPY --from=frontend-builder /app/public/build ./public/build

# Finish composer post-install scripts and dump-autoload
RUN composer dump-autoload --optimize --no-dev

# Setup entrypoint script
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Environment defaults
ENV PORT=8000
ENV APP_ENV=production
ENV APP_DEBUG=false
ENV DB_CONNECTION=sqlite
ENV QUEUE_CONNECTION=database
ENV PYTHON_VOICE_URL=http://voice-core-engine:5001

EXPOSE 8000

HEALTHCHECK --interval=30s --timeout=5s --start-period=15s --retries=3 \
    CMD curl -f http://localhost:8000/api/v1/health || exit 1

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

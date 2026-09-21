#!/bin/sh
set -e

echo "🚀 Starting TailAdmin Voice Core Server..."

# Ensure data and storage directories exist
mkdir -p /app/data/outputs \
         /app/data/uploads \
         /app/data/profiles \
         /app/data/models \
         /app/storage/framework/cache/data \
         /app/storage/framework/sessions \
         /app/storage/framework/views \
         /app/storage/logs \
         /app/database

# Ensure SQLite database file exists
if [ ! -f /app/database/database.sqlite ]; then
    echo "Creating database/database.sqlite..."
    touch /app/database/database.sqlite
fi

# Ensure permissions
chmod -R 775 /app/storage /app/database /app/data 2>/dev/null || true

# Generate application key if not set
if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "" ]; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

# Run database migrations
echo "Running database migrations..."
php artisan migrate --force

# Ensure storage symlink exists
php artisan storage:link --force 2>/dev/null || true

# Generate Swagger/OpenAPI documentation
echo "Generating API documentation..."
php artisan l5-swagger:generate

# Start background queue worker
echo "Starting Laravel Queue Worker in background..."
php artisan queue:work --queue=default,tts,stt,downloads --sleep=2 --timeout=600 --tries=1 &
QUEUE_PID=$!

# Trap signals for graceful shutdown
cleanup() {
    echo "Shutting down services..."
    kill -TERM "$QUEUE_PID" 2>/dev/null || true
    wait "$QUEUE_PID" 2>/dev/null || true
    exit 0
}
trap cleanup SIGTERM SIGINT

# Start Web and API Server in foreground
SERVER_PORT="${PORT:-8000}"
echo "🌐 TailAdmin Voice Core listening on http://0.0.0.0:${SERVER_PORT}"
exec php artisan serve --host=0.0.0.0 --port="${SERVER_PORT}"

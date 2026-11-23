#!/bin/sh
set -e

echo "==> RSV Helmet Production Container Initializing..."

# Create needed directories if they do not exist
mkdir -p /var/www/html/storage/framework/cache/data \
         /var/www/html/storage/framework/sessions \
         /var/www/html/storage/framework/views \
         /var/www/html/storage/logs \
         /var/www/html/storage/app/public \
         /var/www/html/bootstrap/cache

# Fix permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Ensure storage symlink exists
if [ ! -L /var/www/html/public/storage ]; then
    echo "==> Creating storage symlink..."
    php artisan storage:link || true
fi

# Run database migrations if requested
if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "==> Waiting for database connection..."
    max_tries=30
    count=0
    until php -r '
        $host = getenv("DB_HOST") ?: "127.0.0.1";
        $port = getenv("DB_PORT") ?: 3306;
        $db   = getenv("DB_DATABASE");
        $user = getenv("DB_USERNAME");
        $pass = getenv("DB_PASSWORD");
        try {
            $pdo = new PDO("mysql:host={$host};port={$port};dbname={$db}", $user, $pass, [PDO::ATTR_TIMEOUT => 3]);
            exit(0);
        } catch (\Throwable $e) {
            exit(1);
        }
    ' 2>/dev/null; do
        count=$((count + 1))
        if [ $count -gt $max_tries ]; then
            echo "==> Database connection timed out. Continuing without migration..."
            break
        fi
        sleep 2
    done

    if [ $count -le $max_tries ]; then
        echo "==> Running database migrations..."
        php artisan migrate --force --no-interaction || echo "Migration failed, continuing..."
    fi
fi

# Cache configuration, routes, and views for production performance
echo "==> Optimizing Laravel cache..."
php artisan config:cache || true
php artisan route:cache || true
php artisan view:cache || true
php artisan filament:upgrade || true

echo "==> Starting web server & background workers via Supervisor..."
exec "$@"

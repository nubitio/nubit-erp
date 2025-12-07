#!/bin/sh
set -e

# Ensure runtime directories exist and are writable for the web user.
mkdir -p /app/var/cache/${APP_ENV:-prod} /app/var/log /app/public/uploads
chown -R www-data:www-data /app/var /app/public/uploads

# Run database migrations automatically (only in prod/staging, not dev)
if [ "${APP_ENV}" = "prod" ] || [ "${APP_ENV}" = "staging" ]; then
    echo "Running database migrations..."
    
    # Wait for database to be ready (max 30 seconds)
    for i in $(seq 1 30); do
        if php /app/bin/console doctrine:query:sql "SELECT 1" > /dev/null 2>&1; then
            echo "Database is ready"
            break
        fi
        echo "Waiting for database... ($i/30)"
        sleep 1
    done
    
    # Run migrations
    php /app/bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration || {
        echo "Migration failed, but continuing startup..."
    }
    
    echo "Migrations completed"
fi

exec "$@"

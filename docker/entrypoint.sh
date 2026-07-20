#!/bin/sh
set -e

# Clear stale caches so we don't boot with a container compiled against old dependencies
rm -rf /app/var/cache/${APP_ENV:-prod}

# Ensure runtime directories exist and are writable for the web user.
mkdir -p /app/var/cache/${APP_ENV:-prod} /app/var/log /app/public/uploads
chown -R www-data:www-data /app/var /app/public/uploads

# Composer may invoke git during install; trust the bind-mounted app path inside the container.
if command -v git >/dev/null 2>&1; then
    git config --global --add safe.directory /app || true
fi

# Run database migrations. Opt-in only: a single dedicated one-shot "migrate"
# service runs this (RUN_MIGRATIONS=true); web/worker containers never do,
# so scaling replicas or rolling restarts can't race concurrent migrations.
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "Running database migrations..."

    # Wait for database to be ready (max 30 seconds)
    for i in $(seq 1 30); do
        if php /app/bin/console dbal:run-sql "SELECT 1" > /dev/null 2>&1; then
            echo "Database is ready"
            break
        fi
        echo "Waiting for database... ($i/30)"
        sleep 1
    done

    # Run control plane migrations
    php /app/bin/console app:control-plane:migrate --no-interaction || {
        echo "Control plane migration failed, but continuing startup..."
    }

    # Run tenant-aware migrations
    php /app/bin/console app:tenants:migrate --no-interaction || {
        echo "Tenant migration failed, but continuing startup..."
    }

    echo "Migrations completed"
fi

# Drop root privileges before starting the actual process. Skipped in dev:
# /app is a bind mount of the host source tree (arbitrary host UID), and
# staying root there keeps it writable regardless of who owns it on the
# host. stg/prod copy the source into the image at build time and chown it
# to www-data, so dropping privileges is safe — frankenphp already carries
# cap_net_bind_service, so it can still bind :80/:443 as a non-root user.
if [ "${APP_ENV:-prod}" != "dev" ]; then
    exec setpriv --reuid=www-data --regid=www-data --init-groups "$@"
fi

exec "$@"

#!/bin/sh
set -e

# Ensure runtime directories exist and are writable for the web user.
mkdir -p /app/var/cache/${APP_ENV:-prod} /app/var/log /app/public/uploads
chown -R www-data:www-data /app/var /app/public/uploads

exec "$@"

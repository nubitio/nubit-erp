#!/bin/sh

set -eu

# Symfony writes config/reference.php during a debug container compilation.
# Keep the generated type reference committed and fail when dependencies or
# bundle configuration changed without regenerating it.
php bin/console cache:clear --env=dev --no-warmup >/dev/null

if ! git diff --quiet -- config/reference.php; then
    echo 'config/reference.php is stale. Regenerate it with:' >&2
    echo '  php bin/console cache:clear --env=dev' >&2
    git diff -- config/reference.php >&2
    exit 1
fi

echo 'Generated Symfony reference artifacts are up to date.'

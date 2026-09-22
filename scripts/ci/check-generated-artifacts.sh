#!/bin/sh

set -eu

# Symfony writes config/reference.php during a debug container compilation.
# Keep the generated type reference committed and fail when dependencies or
# bundle configuration changed without regenerating it.
php bin/console cache:clear --env=dev --no-warmup >/dev/null
# Symfony's reference dump may settle its generated PHPDoc ordering on the
# second warmup when the cache was produced by a different container state.
# Stabilize that framework-owned artifact before comparing it with git.
php bin/console cache:clear --env=dev --no-warmup >/dev/null

if ! git diff --quiet -- config/reference.php; then
    echo 'config/reference.php is stale. Regenerate it with:' >&2
    echo '  php bin/console cache:clear --env=dev' >&2
    git diff -- config/reference.php >&2
    exit 1
fi

echo 'Generated Symfony reference artifacts are up to date.'

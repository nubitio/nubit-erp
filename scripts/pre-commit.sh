#!/bin/sh

#
# Pre-commit hook: runs Mago (lint + format check) + PHPStan + PHPUnit
# inside the efact-web Docker container.
#

export EFACT_GIT_WORKING_DIR="$(git rev-parse --show-toplevel)"

(cd "./" && docker exec efact-web sh -c 'mago lint && mago fmt --check && php -d memory_limit=1G vendor/bin/phpstan analyse --configuration=phpstan.dist.neon --no-progress && ./vendor/bin/phpunit --no-coverage')

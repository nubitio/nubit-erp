#!/bin/sh

#
# Run the hook command.
# Note: this will be replaced by the real command during copy.
#

# GrumPHP env vars
export GRUMPHP_GIT_WORKING_DIR="$(git rev-parse --show-toplevel)"

# Web container name
WEB_CONTAINER="web"

# Path to the directory where the composer.json file is located inside the container
COMPOSER_PATH="/var/www/html/"

# Temporary file to store the git diff
TEMP_FILE="/tmp/grumphp_diff.txt"

# Fetch the GIT diff and save it to a temporary file
git -c diff.mnemonicprefix=false -c diff.noprefix=false --no-pager diff -r -p -m -M --full-index --no-color --staged > "$TEMP_FILE"

# Copy the diff file into the container
docker cp "$TEMP_FILE" "$WEB_CONTAINER:$TEMP_FILE"

# Run GrumPHP inside the container using the copied diff file
docker exec "$WEB_CONTAINER" sh -c "cd $COMPOSER_PATH && cat $TEMP_FILE | vendor/bin/grumphp git:pre-commit --skip-success-output"

# Clean up temporary file
rm -f "$TEMP_FILE"

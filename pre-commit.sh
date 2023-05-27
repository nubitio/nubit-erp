#!/bin/sh

# Web container name
WEB_CONTAINER="efact-web"

# Path to the directory where the composer.json file is located inside the container
COMPOSER_PATH="/var/www/html/"

# Function to execute commands inside the web container
run_in_container() {
  docker exec "$WEB_CONTAINER" sh -c "cd $COMPOSER_PATH && $1"
}

# Function to check the result of the previous command
check_result() {
  if [ $? -ne 0 ]; then
    echo ""
    echo "****************************************************"
    echo "Errors found! Commit cannot proceed."
    echo "****************************************************"
    echo ""
    exit 1
  fi
}

# Run phpstan inside the web container
run_in_container "vendor/bin/phpstan analyse --no-progress --no-interaction --memory-limit=2G"
check_result

# Run php-cs-fixer inside the web container
run_in_container "vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff"
check_result

# Run phpunit inside the web container
run_in_container "bin/phpunit"
check_result

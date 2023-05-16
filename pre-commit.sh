#!/bin/sh

# Nombre del contenedor web
WEB_CONTAINER="efact-web"

# Ruta al directorio donde se encuentra el archivo composer.json dentro del contenedor
COMPOSER_PATH="/var/www/html/"

# Ejecutar phpstan dentro del contenedor web
docker exec "$WEB_CONTAINER" sh -c "cd $COMPOSER_PATH && vendor/bin/phpstan analyse --memory-limit=2G --no-progress"

# Verificar el resultado del comando anterior
if [ $? -ne 0 ]; then
  echo ""
  echo "****************************************************"
  echo "¡Se encontraron errores de phpstan! No se puede realizar el commit."
  echo "****************************************************"
  echo ""
  exit 1
fi

# Ejecutar php-cs-fixer dentro del contenedor web
docker exec "$WEB_CONTAINER" sh -c "cd $COMPOSER_PATH && vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff"

# Verificar el resultado del comando anterior
if [ $? -ne 0 ]; then
  echo ""
  echo "****************************************************"
  echo "¡Se encontraron errores de php-cs-fixer! No se puede realizar el commit."
  echo "****************************************************"
  echo ""
  exit 1
fi

# Ejecutar phpunit dentro del contenedor web
docker exec "$WEB_CONTAINER" sh -c "cd $COMPOSER_PATH && bin/phpunit"

# Verificar el resultado del comando anterior
if [ $? -ne 0 ]; then
  echo ""
  echo "****************************************************"
  echo "¡Se encontraron errores de PHPUnit! No se puede realizar el commit."
  echo "****************************************************"
  echo ""
  exit 1
fi

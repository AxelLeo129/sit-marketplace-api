#!/bin/sh
# Arranque del contenedor web. Las migraciones no van aquí: Railway las corre antes
# de cada despliegue (preDeployCommand en railway.json).
set -e

# Cachea configuración, rutas, eventos y vistas con las variables del entorno.
php artisan optimize

exec frankenphp php-server --root public --listen ":${PORT:-8080}"

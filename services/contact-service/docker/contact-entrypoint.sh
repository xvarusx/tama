#!/bin/sh
set -eu

php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
exec /usr/local/bin/register.sh

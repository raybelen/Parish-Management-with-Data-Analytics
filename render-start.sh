#!/bin/sh

set -eu

render_port="${PORT:-10000}"

sed -ri "s/^Listen [0-9]+$/Listen ${render_port}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${render_port}>/" /etc/apache2/sites-available/000-default.conf

php artisan migrate --force --no-interaction
php artisan config:cache --no-interaction
php artisan view:cache --no-interaction

exec apache2-foreground

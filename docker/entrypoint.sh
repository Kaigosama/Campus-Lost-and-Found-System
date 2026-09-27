#!/bin/sh
set -e

# Railway (and most hosts) pick the port through $PORT; locally Apache stays on 80.
PORT="${PORT:-80}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# A mounted volume replaces public/uploads and usually arrives owned by root; Apache (www-data) writes photos there.
mkdir -p /var/www/html/public/uploads
chown www-data:www-data /var/www/html/public/uploads

php /var/www/html/docker/init-db.php

exec "$@"

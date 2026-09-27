#!/bin/sh
set -e

# Railway (and most hosts) pick the port through $PORT; locally Apache stays on 80.
PORT="${PORT:-80}"
sed -ri "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Apache must load exactly one MPM, and mod_php needs prefork. Railway has started this image with a second
# MPM enabled ("AH00534: More than one MPM loaded"), so switch off any other one here.
for mod in /etc/apache2/mods-enabled/mpm_*.load; do
    [ -e "$mod" ] || continue
    name=$(basename "$mod" .load)
    if [ "$name" != mpm_prefork ]; then
        echo "[entrypoint] Disabling Apache module $name (only mpm_prefork works with PHP)"
        rm -f "/etc/apache2/mods-enabled/$name.load" "/etc/apache2/mods-enabled/$name.conf"
    fi
done
[ -e /etc/apache2/mods-enabled/mpm_prefork.load ] || a2enmod -q mpm_prefork

# A mounted volume replaces public/uploads and usually arrives owned by root; Apache (www-data) writes photos there.
mkdir -p /var/www/html/public/uploads
chown www-data:www-data /var/www/html/public/uploads

php /var/www/html/docker/init-db.php

exec "$@"

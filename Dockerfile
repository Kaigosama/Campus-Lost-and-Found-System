FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql \
    && a2enmod headers \
    && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini "$PHP_INI_DIR/conf.d/zz-clafs.ini"
COPY docker/apache.conf /etc/apache2/conf-enabled/zz-clafs.conf

WORKDIR /var/www/html
COPY . .

# Strip Windows line endings in case the script was checked out with CRLF.
RUN sed -i 's/\r$//' docker/entrypoint.sh \
    && chmod +x docker/entrypoint.sh \
    && mkdir -p public/uploads \
    && chown -R www-data:www-data public/uploads

EXPOSE 80
ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]
CMD ["apache2-foreground"]

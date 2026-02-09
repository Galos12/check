FROM php:8.2-apache

RUN apt-get update && apt-get install -y libsqlite3-dev && docker-php-ext-install pdo pdo_sqlite

ENV APACHE_DOCUMENT_ROOT /var/www/html

RUN sed -ri -e 's!/var/www/html!'"$APACHE_DOCUMENT_ROOT"'!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!'/var/www/html'"'!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf || true

WORKDIR /var/www/html

COPY . /var/www/html
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh && chown -R www-data:www-data /var/www/html

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["apache2-foreground"]

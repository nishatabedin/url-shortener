FROM php:8.3-fpm-alpine

RUN apk add --no-cache nginx bash curl git icu-dev oniguruma-dev libzip-dev $PHPIZE_DEPS \
    && docker-php-ext-install pdo_mysql mbstring intl zip opcache \
    && pecl install redis \
    && docker-php-ext-enable redis

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

COPY docker/nginx.conf /etc/nginx/http.d/default.conf

EXPOSE 80

CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]

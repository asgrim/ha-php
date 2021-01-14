FROM php:7.4-cli-alpine AS base

RUN apk --no-cache add postgresql postgresql-dev bash \
    && docker-php-ext-install pdo pdo_pgsql pgsql

WORKDIR /app

ADD bin /app/bin
ADD public /app/public
ADD src /app/src
ADD composer.json /app/composer.json
ADD composer.lock /app/composer.lock

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

RUN composer install --no-dev

ENTRYPOINT []

FROM base AS worker
CMD ["bin/ha-php"]

FROM base AS web
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public", "public/index.php"]

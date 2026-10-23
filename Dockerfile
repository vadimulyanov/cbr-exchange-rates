FROM php:8.5-fpm-bookworm AS base

RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev librabbitmq-dev libicu-dev unzip git \
    && docker-php-ext-install -j$(nproc) pdo_pgsql bcmath pcntl intl \
    && curl -fsSL https://codeload.github.com/php-amqp/php-amqp/tar.gz/refs/tags/v2.2.0 -o /tmp/amqp.tar.gz \
    && echo '0fc9a23b54010a9ba475a1988d590b578942dbdf14c19508fd47dd09e852ab0f  /tmp/amqp.tar.gz' | sha256sum -c - \
    && mkdir /tmp/amqp && tar -xzf /tmp/amqp.tar.gz -C /tmp/amqp --strip-components=1 \
    && cd /tmp/amqp && phpize && ./configure && make -j$(nproc) && make install \
    && docker-php-ext-enable amqp \
    && rm -rf /tmp/amqp /tmp/amqp.tar.gz /var/lib/apt/lists/*

COPY --from=composer:2.9 /usr/bin/composer /usr/local/bin/composer
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini
WORKDIR /app
ENV COMPOSER_IPRESOLVE=4

FROM base AS dependencies
COPY composer.json composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-progress

FROM base AS app
COPY --chown=www-data:www-data . .
COPY --from=dependencies --chown=www-data:www-data /app/vendor ./vendor
RUN php bin/console assets:install public --no-interaction \
    && mkdir -p var/cache var/log && chown -R www-data:www-data var
USER www-data
CMD ["php-fpm", "-F"]

FROM nginx:1.28-alpine AS web
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /app/public /app/public
RUN sed -i '/^user /d; s@^pid .*;@pid /tmp/nginx.pid;@' /etc/nginx/nginx.conf \
    && chown -R nginx:nginx /var/cache/nginx /etc/nginx/conf.d
USER nginx

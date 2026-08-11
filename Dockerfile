FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-scripts --no-interaction --prefer-dist

FROM dunglas/frankenphp:1-php8.4 AS runtime
WORKDIR /app

ENV APP_ENV=prod \
    SERVER_NAME=:8080 \
    FRANKENPHP_CONFIG="worker ./public/index.php"

COPY --from=vendor /app/vendor ./vendor
COPY config ./config
COPY public ./public
COPY src ./src
COPY composer.json composer.lock .env.example ./

RUN mkdir -p var && chown -R www-data:www-data var

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=3s --start-period=5s \
    CMD curl -fsS http://127.0.0.1:8080/healthz || exit 1

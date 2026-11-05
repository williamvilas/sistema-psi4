# syntax=docker/dockerfile:1

ARG PHP_VERSION=8.5

# ---------------------------------------------------------------------------
# Stage: vendor (dependências PHP via Composer)
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./

RUN composer install \
    --no-dev \
    --no-autoloader \
    --no-scripts \
    --ignore-platform-reqs \
    --prefer-dist

# ---------------------------------------------------------------------------
# Stage: base (PHP CLI + Swoole + extensões, sem código-fonte)
# Usada diretamente pelo stage, que monta o código via bind mount. A
# aplicação roda no Laravel Octane (Swoole), atrás do nginx (proxy_pass).
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-cli AS base

WORKDIR /var/www/html

RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libxml2-dev \
        libonig-dev \
        libcurl4-openssl-dev \
        libbrotli-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        soap \
        curl \
    && pecl install redis \
    && yes '' | pecl install swoole \
    && docker-php-ext-enable redis swoole \
    && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY .docker/php/conf.d/custom.ini /usr/local/etc/php/conf.d/zz-custom.ini
COPY .docker/php/entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

ENTRYPOINT ["docker-entrypoint.sh"]

# Workers síncronos: cada um atende uma requisição por vez, então em uma
# aplicação limitada por I/O (MySQL, S3, Google, Mercado Pago) vale ter mais
# workers que núcleos. --max-requests recicla o worker e contém vazamentos de
# memória (Dompdf, Excel).
ENV OCTANE_WORKERS=auto \
    OCTANE_TASK_WORKERS=1 \
    OCTANE_MAX_REQUESTS=500 \
    OCTANE_SWOOLE_USER=www-data

EXPOSE 8000

CMD ["sh", "-c", "exec php artisan octane:start --server=swoole --host=0.0.0.0 --port=8000 --workers=${OCTANE_WORKERS} --task-workers=${OCTANE_TASK_WORKERS} --max-requests=${OCTANE_MAX_REQUESTS}"]

# ---------------------------------------------------------------------------
# Stage: dev (ambiente local)
# Igual à base, mais o Node para o `octane:start --watch` (o chokidar vem do
# node_modules do projeto, montado via bind mount), que recarrega os workers
# a cada alteração em PHP.
# ---------------------------------------------------------------------------
FROM base AS dev

RUN apt-get update && apt-get install -y --no-install-recommends nodejs \
    && rm -rf /var/lib/apt/lists/*

# ---------------------------------------------------------------------------
# Stage: app (imagem final para produção, com código e build assados)
#
# Os assets de front-end (public/css, public/js etc.) NÃO são recompilados
# aqui: o `laravel-mix@6` tem um bug conhecido e não resolvido no upstream
# (mix.version() falha com ENOENT lendo um caminho absoluto a partir da raiz
# do filesystem, ex.: "/public/css/app.css") que se manifesta de forma
# intermitente em builds containerizados
# (https://github.com/JeffreyWay/laravel-mix/issues/939,
# https://github.com/JeffreyWay/laravel-mix/issues/1607). A imagem usa os
# assets já commitados em `public/` (gerados localmente com `npm run prod`,
# conforme o checklist da seção 12 do CLAUDE.md) — rode `npm run prod` antes
# do deploy sempre que `resources/js`/`resources/css` mudar.
# ---------------------------------------------------------------------------
FROM base AS app

COPY . .
COPY --from=vendor /app/vendor ./vendor

RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && php artisan package:discover --ansi \
    && cp -a public /var/www/html-public-src \
    && mkdir -p storage/framework/{sessions,views,cache/data} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

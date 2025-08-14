FROM php:8.3-fpm-alpine

# 系統套件與 PHP 擴充
RUN apk add --no-cache icu-dev libzip-dev oniguruma-dev git bash curl-dev $PHPIZE_DEPS \
 && docker-php-ext-install pdo pdo_mysql intl zip bcmath opcache

# OPcache（建議開啟）
RUN { \
  echo "opcache.enable=1"; \
  echo "opcache.enable_cli=0"; \
  echo "opcache.jit=1255"; \
  echo "opcache.jit_buffer_size=64M"; \
  echo "opcache.validate_timestamps=0"; \
  echo "opcache.memory_consumption=128"; \
  echo "opcache.max_accelerated_files=20000"; \
} > /usr/local/etc/php/conf.d/opcache.ini

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

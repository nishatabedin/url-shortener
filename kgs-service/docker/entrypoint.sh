#!/usr/bin/env sh
set -e

if [ "${APP_ENV:-}" = "production" ]; then
  if [ -f artisan ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
  fi

  if command -v composer >/dev/null 2>&1; then
    composer dump-autoload --no-dev --optimize
  fi
fi

exec sh -c "php-fpm -D && nginx -g 'daemon off;'"

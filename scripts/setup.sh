#!/usr/bin/env sh
set -eu
cd "$(dirname "$0")/.."
[ -f .env ] || cp .env.example .env
docker compose build laravel.test
docker compose up -d mysql
docker compose run --rm laravel.test composer install --no-interaction
docker compose run --rm --no-deps vite npm ci
docker compose run --rm --no-deps vite npm run build
if grep -q '^APP_KEY=$' .env; then
  docker compose run --rm laravel.test php artisan key:generate --no-interaction
fi
docker compose run --rm laravel.test php artisan migrate --seed --no-interaction
if [ "${1:-}" = "--demo" ]; then
  docker compose run --rm laravel.test php artisan db:seed --class=DemoSeeder --no-interaction
fi
docker compose up -d laravel.test worker
printf '%s\n' 'Mesa de ayuda disponible en http://localhost:8088'

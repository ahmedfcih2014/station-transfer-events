#!/usr/bin/env bash
# Run Laravel with PHP's built-in server. Requires PHP, Composer, and PostgreSQL
# (create database station_events or adjust DB_* in station-events/.env).
# Usage: ./scripts/run-local.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APP="$ROOT/station-events"
cd "$APP"

if ! command -v php >/dev/null 2>&1; then
  echo "php is required but not installed or not on PATH." >&2
  exit 1
fi
if ! command -v composer >/dev/null 2>&1; then
  echo "composer is required but not installed or not on PATH." >&2
  exit 1
fi

if [[ ! -f .env ]]; then
  cp .env.example .env
  echo "Created .env from .env.example — edit DB_* if your Postgres credentials differ."
fi

composer install --no-interaction

if ! grep -qE '^APP_KEY=base64:' .env 2>/dev/null; then
  php artisan key:generate --no-interaction
fi

php artisan migrate --force

echo ""
echo "Starting server at http://127.0.0.1:8000"
echo "Ensure PostgreSQL is running and the database exists (see README)."
echo ""

exec php artisan serve

#!/usr/bin/env bash
# Run the stack from the repository root: Nginx + PHP-FPM + PostgreSQL.
# Usage: ./scripts/run-docker.sh
# Optional: HTTP_PORT=3000 ./scripts/run-docker.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

if ! command -v docker >/dev/null 2>&1; then
  echo "docker is required but not installed or not on PATH." >&2
  exit 1
fi

docker compose up -d --build
docker compose exec -T app php artisan migrate --force

PORT="${HTTP_PORT:-8080}"
echo ""
echo "Stack is up. API (default): http://localhost:${PORT}"
echo "Examples: GET http://localhost:${PORT}/stations/S1/summary"
echo "Stop: docker compose down   (add -v to drop Postgres data)"
echo ""

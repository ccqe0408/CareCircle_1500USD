#!/usr/bin/env bash
set -euo pipefail
project_root="$(cd "$(dirname "$0")/.." && pwd -P)"
cd "$project_root/backend"
if [[ ! -f .env ]]; then cp .env.example .env; fi
composer install --no-interaction
if ! rg -q '^APP_KEY=base64:' .env; then php artisan key:generate; fi
npm ci
npm run build
php artisan migrate --force
php artisan db:seed --force
cd "$project_root/mobile"
flutter pub get
echo 'Setup complete. Run scripts/start-local.sh from the project root.'

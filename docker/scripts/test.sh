#!/usr/bin/env bash
# Full backend test suite: PHPUnit (test DB) and Behat (running stack, dev DB).
# Requires a running stack: docker compose -f docker/compose.yml up -d
set -euo pipefail

compose=(docker compose -f "$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/compose.yml")
php=("${compose[@]}" exec -T -u "$(id -u)" php)

"${php[@]}" vendor/bin/phpunit
"${php[@]}" vendor/bin/behat --no-interaction

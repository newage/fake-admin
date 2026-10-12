#!/usr/bin/env bash
# End-to-end API check on a CLEAN DB: deploy → superadmin's first login → list →
# refresh → logout → manager sees sensors → user gets 403.
# WARNING: deletes the mysql-data volume (dev data is recreated by the commands below).
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
compose=(docker compose -f "$root/docker/compose.yml")
php=("${compose[@]}" exec -T -u "$(id -u)" php)
base="http://localhost:${HTTP_PORT:-8080}"
admin_email="$(grep -E '^SUPERADMIN_EMAIL=' "$root/.env" | cut -d= -f2)"

fail() { echo "FAIL: $*" >&2; exit 1; }
json() { python3 -c "import sys,json; print(json.load(sys.stdin)$1)"; }
token() { curl -s -X POST "$base/token" -d client_id=fake-admin-spa "$@"; }
status() { curl -s -o /dev/null -w '%{http_code}' "$@"; }

echo "== розгортання"
"${compose[@]}" down -v >/dev/null 2>&1
"${compose[@]}" up -d >/dev/null 2>&1
until "${compose[@]}" exec -T mysql mysqladmin ping -h127.0.0.1 -uroot -proot --silent >/dev/null 2>&1; do sleep 2; done
sleep 5
"${php[@]}" vendor/bin/doctrine-migrations migrate --no-interaction >/dev/null
"${php[@]}" bin/console app:superadmin:ensure
"${php[@]}" bin/console app:sensors:seed >/dev/null
"${php[@]}" bin/console app:user:create manager@example.com --role=manager --password='manager-pass-1' >/dev/null
"${php[@]}" bin/console app:user:create user@example.com --role=user --password='user-pass-12' >/dev/null

echo "== перший вхід суперадміна"
r=$(token -d grant_type=password -d username="$admin_email" -d password='admin-pass-123')
admin_access=$(echo "$r" | json "['access_token']"); admin_refresh=$(echo "$r" | json "['refresh_token']")
[ "$(token -d grant_type=password -d username="$admin_email" -d password='attacker-pass-1' | json "['error']")" = invalid_grant ] || fail "пароль суперадміна змінено повторно"

echo "== список датчиків"
total=$(curl -s "$base/api/sensors" -H "Authorization: Bearer $admin_access" | json "['total']")
[ "$total" = 5000 ] || fail "очікувалось 5000 датчиків, є $total"

echo "== refresh і logout"
r2=$(token -d grant_type=refresh_token -d refresh_token="$admin_refresh")
admin_refresh2=$(echo "$r2" | json "['refresh_token']")
[ "$(token -d grant_type=refresh_token -d refresh_token="$admin_refresh" | json "['error']")" = invalid_grant ] || fail "старий refresh працює"
[ "$(status -X POST "$base/token/revoke" -d token="$admin_refresh2")" = 200 ] || fail "revoke"
[ "$(token -d grant_type=refresh_token -d refresh_token="$admin_refresh2" | json "['error']")" = invalid_grant ] || fail "refresh після logout працює"

echo "== manager і user"
mgr=$(token -d grant_type=password -d username=manager@example.com -d password='manager-pass-1' | json "['access_token']")
[ "$(status "$base/api/sensors?page_size=1" -H "Authorization: Bearer $mgr")" = 200 ] || fail "manager не бачить датчики"
usr=$(token -d grant_type=password -d username=user@example.com -d password='user-pass-12' | json "['access_token']")
[ "$(status "$base/api/sensors" -H "Authorization: Bearer $usr")" = 403 ] || fail "user має отримати 403"
[ "$(status "$base/api/sensors")" = 401 ] || fail "без токена має бути 401"

echo "OK"

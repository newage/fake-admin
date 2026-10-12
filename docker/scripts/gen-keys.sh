#!/usr/bin/env bash
# Creates .env from .env.example (if missing) and fills in empty OAuth keys.
set -euo pipefail

root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
env_file="$root/.env"

[ -f "$env_file" ] || cp "$root/.env.example" "$env_file"

set_if_empty() {
    local key="$1" value="$2"
    if grep -qE "^${key}=$" "$env_file"; then
        sed -i "s|^${key}=$|${key}=${value}|" "$env_file"
        echo "set ${key}"
    else
        echo "skip ${key} (already set)"
    fi
}

tmp="$(mktemp -d)"
trap 'rm -rf "$tmp"' EXIT

openssl genpkey -algorithm RSA -pkeyopt rsa_keygen_bits:2048 -out "$tmp/private.pem" 2>/dev/null
openssl rsa -in "$tmp/private.pem" -pubout -out "$tmp/public.pem" 2>/dev/null

set_if_empty OAUTH_PRIVATE_KEY_B64 "$(base64 -w0 "$tmp/private.pem")"
set_if_empty OAUTH_PUBLIC_KEY_B64 "$(base64 -w0 "$tmp/public.pem")"
set_if_empty OAUTH_ENCRYPTION_KEY "$(openssl rand -base64 32)"

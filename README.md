# fake-admin

Admin panel over sensor data: PHP 8.4 + Mezzio (backend) and Vite + React + TS (frontend). Everything runs in Docker; run commands from the repository root.

## Backend

One command (keys, stack, composer, migrations, superadmin, sensor seed):

```bash
make -C docker build
```

Or step by step:

```bash
docker/scripts/gen-keys.sh                       # creates .env and OAuth keys (once)
docker compose -f docker/compose.yml up -d --build

PHP="docker compose -f docker/compose.yml exec -T -u $(id -u) php"
$PHP composer install
$PHP vendor/bin/doctrine-migrations migrate --no-interaction
$PHP bin/console app:superadmin:ensure           # superadmin from SUPERADMIN_EMAIL (password is set on first login)
$PHP bin/console app:sensors:seed                # ≈5,000 fake sensors
```

The API is available at http://localhost:8080 (change the port via `HTTP_PORT` in `.env`).

Stop: `docker compose -f docker/compose.yml down` (with `-v` it also deletes the DB).

## Frontend

The frontend runs in a separate `node` container (`tools` profile). The backend stack must be running — Vite proxies `/api` and `/token` to nginx.

```bash
NODE="docker compose -f docker/compose.yml run --rm -T -u $(id -u) -e HOME=/tmp node"
$NODE npm install                                # once, and after dependencies change

# dev server accessible from the host → http://localhost:5173
docker compose -f docker/compose.yml run --rm -u $(id -u) -e HOME=/tmp -p 5173:5173 node npm run dev
```

## Rebuilding the frontend

```bash
$NODE npm run build                              # tsc + vite build → frontend/dist
$NODE sh -c "npm run lint && npx vitest run && npm run build"   # full check + build
```

The build goes to `frontend/dist`, which nginx serves at http://localhost:8080 (a volume in `docker/compose.yml`). After `npm run build`, refresh the page — no restart needed. If `frontend/dist` did not exist before the first `up`, run `docker compose -f docker/compose.yml up -d --force-recreate nginx`.

## Tests

```bash
docker/scripts/test.sh                           # PHPUnit + Behat
$NODE npx vitest run                             # frontend tests
```

For more on commands and architecture, see [CLAUDE.md](CLAUDE.md) and `docs/`.

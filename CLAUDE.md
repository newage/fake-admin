# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Status

`fake-admin` — адмінка над даними датчиків. Зараз у репозиторії:

- `docker/` — compose-стек (nginx, php-fpm 8.4, mysql 8.4, fluent-bit; `node` — профіль `tools`, на вимогу).
- `backend/` — PHP 8.4 + Mezzio (скелет, Monolog, phpdotenv, Doctrine DBAL/Migrations, PHPUnit, Behat). Код додатку в `backend/src/App/`.
- `frontend/` — Vite + React + TS + Tailwind 4 + shadcn/ui, TanStack Router/Query/Table, RHF + Zod, Vitest (зміна `add-admin-frontend`). Код у `frontend/src/` (`routes/`, `features/`, `components/`, `lib/`, `styles/`).
- `openspec/` — зміни й специфікації (OpenSpec, `/opsx:*`); `docs/adr/` — ADR; `.agents/docs/` — вихідне ТЗ і журнал рішень.

Локальна розробка тільки в Docker. Усі команди з кореня репозиторію.

## Commands

```bash
# Стек (HTTP на http://localhost:8080, зміни порту: HTTP_PORT у .env)
docker/scripts/gen-keys.sh                       # створює .env і OAuth-ключі (один раз)
docker compose -f docker/compose.yml up -d --build
docker compose -f docker/compose.yml ps
docker compose -f docker/compose.yml logs fluent-bit   # access-логи nginx
docker compose -f docker/compose.yml down              # -v також видаляє БД

# Скорочення для команд у php-контейнері від вашого uid (щоб файли не належали root)
PHP="docker compose -f docker/compose.yml exec -T -u $(id -u) php"
$PHP composer install
$PHP composer require <пакет>                     # у контейнері COMPOSER_HOME може знадобитись: -e COMPOSER_HOME=/tmp/composer

# БД і дані (див. docs/api.md, розділ «Консольні команди»)
$PHP vendor/bin/doctrine-migrations migrate --no-interaction
$PHP bin/console app:superadmin:ensure                    # суперадмін з SUPERADMIN_EMAIL, пароль — першим входом
$PHP bin/console app:sensors:seed                         # ≈5 000 фейкових датчиків
$PHP bin/console app:user:create m@example.com --role=manager --password='…'

# Тести
docker/scripts/test.sh                                              # PHPUnit + Behat разом
docker/scripts/e2e-api.sh                                           # наскрізний прохід API на чистій БД (видаляє том mysql-data!)
$PHP vendor/bin/phpunit                                              # усі PHPUnit
$PHP vendor/bin/phpunit --filter PingHandlerTest                     # один тест
$PHP vendor/bin/behat --no-interaction                               # усі Behat
$PHP vendor/bin/behat --no-interaction features/ping.feature         # один feature
$PHP vendor/bin/behat --no-interaction features/ping.feature:2       # один сценарій
```

Frontend (node-контейнер із профілю `tools`; усі команди з кореня репозиторію):

```bash
NODE="docker compose -f docker/compose.yml run --rm -T -u $(id -u) -e HOME=/tmp node"
$NODE npm install
$NODE npm run dev                                   # Vite на :5173, проксі /api і /token на nginx (стек має працювати)
docker compose -f docker/compose.yml run --rm -u $(id -u) -e HOME=/tmp -p 5173:5173 node npm run dev   # те саме з доступом з хоста
$NODE npx vitest run                                # усі тести
$NODE npx vitest run src/lib/api-client.test.ts     # один файл
$NODE npx vitest run -t "частина назви тесту"       # один тест
$NODE npm run lint                                  # ESLint (забороняє dangerouslySetInnerHTML/innerHTML)
$NODE npm run build                                 # tsc + vite build у frontend/dist
$NODE sh -c "npm run lint && npx vitest run && npm run build"   # повний набір перед завершенням
```

Нотатки:
- Frontend: refresh-токен у localStorage, access у памʼяті (`frontend/src/lib/session.ts`); усі запити через `lib/api-client.ts` (одноразовий refresh при 401). Стан таблиці датчиків живе в URL (`features/sensors/search.ts`). Роль із claims лише ховає пункти меню, права визначає сервер (403). CSP задано в `docker/nginx/default.conf`.
- Behat 4: конфіг лише `backend/behat.php` (YAML не підтримується), кроки описуються PHP-атрибутами `#[When]`/`#[Then]`. Без `--no-interaction` на невизначених кроках чекає введення й зависає.
- PHPUnit-тести БД працюють у `fake_admin_test` (створюється разом із томом mysql-data); Behat — у dev-БД `fake_admin`, свої дані створює з доменом `@behat.test` / кодами `BHT-` і прибирає.
- Auth: `league/oauth2-server` через `mezzio-authentication-oauth2`; репозиторії в `backend/src/App/Auth/Repository/`. RBAC: права маршруту — `->setOptions(['permission' => '…'])` у `config/routes.php` і рядок у `role_permissions`; маршрут без `permission` закритий (403).
- Behat ходить на `http://nginx` (змінна `API_BASE_URL`), тож стек має працювати.
- Dev-режим Mezzio вимикає кеш конфігурації (`composer development-enable`); після зміни DI-конфігурації в production-режимі виконати `composer clear-config-cache`.

# Proposal

## Why

У MySQL лежать сирі дані тисяч датчиків (поки фейкові), але API для їх перегляду й розмежування доступу немає: `backend/` і `docker/` порожні. Потрібен backend-зріз: середовище, вхід за ролями, список датчиків і контракт для розрахунків. Frontend адмінки винесено в окрему зміну `add-admin-frontend`, яка залежить від цієї.

## What Changes

- Docker-середовище для локальної розробки: nginx (`/` → статика SPA, `/api/*` і `/token` → PHP), PHP-FPM 8.4, MySQL, node, fluent-bit для логів nginx.
- Backend (PHP 8.4 + Mezzio): OAuth 2.0 (`league/oauth2-server`, password + refresh grant), access JWT RS256 ≤5 хв, refresh з ротацією, хешем у БД і відкликанням.
- RBAC у middleware за матрицею «роль × ендпоінт» у БД (`role_permissions`, `constraints` зарезервоване).
- `GET /api/sensors`: серверна пагінація (OFFSET), сортування, фільтри (ADR 0001); доступ `admin` і `manager`.
- Схема БД через Doctrine Migrations (без ORM), сидер ≈5 000 фейкових датчиків, суперадмін з `SUPERADMIN_EMAIL` з порожнім паролем (пароль встановлюється першим входом), консольна команда створення користувача.
- Контракт калькулятора (`live`/`cached`) і `GET /api/sensors/{id}/readings`.
- PHPUnit і Behat; `docs/api.md`.

Поза межами: усе, що в `add-admin-frontend`; екрани керування користувачами, сесіями й правами; створення датчика й HMAC-секрет; консʼюмери, дедуплікація показань; воркер `calc_results`; конкретні калькулятори; права ролі `user`.

## Capabilities

### New Capabilities
- `user-auth`: вхід паролем, видача й оновлення access/refresh, ротація та відкликання refresh, перший вхід суперадміна.
- `role-access`: перевірка ролі на захищених маршрутах за матрицею прав у БД.
- `sensor-list`: `GET /api/sensors` із серверними пагінацією, сортуванням і фільтрами.
- `sensor-calculators`: контракт калькуляторів (`live`/`cached`) і ендпоінт сирих показань.

### Modified Capabilities

(немає)

## Impact

- Нові каталоги `backend/`, `docker/`; оновити `CLAUDE.md` командами запуску й тестів.
- Нові API: `POST /token`, logout, `GET /api/sensors`, `GET /api/sensors/{id}/readings`; їх споживає `add-admin-frontend`.
- Залежності: Mezzio, `league/oauth2-server`, `mezzio/mezzio-authentication-oauth2`, `lcobucci/jwt`, Doctrine DBAL + Migrations, `monolog/monolog`, `vlucas/phpdotenv`, PHPUnit, Behat.
- Секрети в `.env`: RSA-ключі, `SUPERADMIN_EMAIL`.
- ADR `docs/adr/0001-server-side-offset-pagination-for-sensors-list.md` (proposed).

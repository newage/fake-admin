# API

Базовий URL у локальному стеку: `http://localhost:8080` (nginx; SPA на `/`, PHP на `/api/*`, `/token`, `/token/revoke`).
Усі відповіді API — JSON. Час скрізь у UTC, формат `2026-10-11T18:41:05Z`.

## Автентифікація

OAuth 2.0 з єдиним публічним клієнтом `fake-admin-spa` (без секрету). Потік: password grant + refresh grant.
Access-токен — JWT (RS256) на 5 хвилин; роль користувача лежить у його claim `scopes` як `role:<імʼя>`.
Refresh-токен діє 30 днів, **одноразовий**: кожне оновлення видає нову пару й робить старий недійсним.

### `POST /token` — вхід

`application/x-www-form-urlencoded` (JSON теж приймається).

| Поле | Значення |
|---|---|
| `grant_type` | `password` |
| `client_id` | `fake-admin-spa` |
| `username` | email |
| `password` | пароль |

```bash
curl -s -X POST http://localhost:8080/token \
  -d grant_type=password -d client_id=fake-admin-spa \
  -d username=manager@example.com -d password='manager-pass-1'
```

Відповідь `200`:

```json
{"token_type": "Bearer", "expires_in": 300, "access_token": "eyJ0…", "refresh_token": "def50…"}
```

| Код | Коли |
|---|---|
| 200 | токени видано |
| 400 | `invalid_grant`: невірні дані, невідомий email або заблокований користувач (відповіді однакові); `invalid_request`: перший пароль суперадміна коротший за 8 символів |
| 401 | `invalid_client`: невірний `client_id` |
| 400 | `unsupported_grant_type`: grant не `password`/`refresh_token` |

**Перший вхід суперадміна.** Користувач із `SUPERADMIN_EMAIL` створюється командою `app:superadmin:ensure` з порожнім паролем. Перший успішний запит `POST /token` з цим email встановлює пароль (мінімум 8 символів); після цього встановити пароль повторно не можна.

### `POST /token` — оновлення

| Поле | Значення |
|---|---|
| `grant_type` | `refresh_token` |
| `client_id` | `fake-admin-spa` |
| `refresh_token` | поточний refresh-токен |

```bash
curl -s -X POST http://localhost:8080/token \
  -d grant_type=refresh_token -d client_id=fake-admin-spa -d refresh_token="$REFRESH"
```

Відповідь така сама, як при вході. `400 invalid_grant`, якщо токен використано, відкликано, він прострочений або користувача заблоковано. Роль у новому access береться з БД на момент оновлення.

### `POST /token/revoke` — вихід (RFC 7009)

Тіло: `token=<refresh_token>`. Заголовок `Authorization` не потрібен. Відповідь завжди `200` з порожнім тілом, навіть для невідомого чи зіпсованого токена.

```bash
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://localhost:8080/token/revoke -d token="$REFRESH"
```

## Захищені маршрути `/api/*`

Заголовок `Authorization: Bearer <access_token>`. Єдиний публічний маршрут — `GET /api/ping`.

| Код | Коли |
|---|---|
| 401 `{"error":"unauthorized"}` | немає токена, він прострочений чи підроблений (також для неіснуючих шляхів `/api/*`) |
| 403 `{"error":"forbidden"}` | роль не має права на цей маршрут (матриця в таблиці `role_permissions`) |

Початкова матриця: `admin` і `manager` мають `sensors.list` та `sensors.readings`; `user` не має прав.

### `GET /api/ping`

Публічний, без токена. `200 {"ack": <unix-time>}`.

### `GET /api/sensors` — список датчиків

Право: `sensors.list`. Пагінація OFFSET, сортування й фільтри на сервері ([ADR 0001](adr/0001-server-side-offset-pagination-for-sensors-list.md)).

| Параметр | За замовчуванням | Правила |
|---|---|---|
| `page` | `1` | ціле ≥ 1 |
| `page_size` | `25` | ціле від 1 до 100 |
| `sort` | `created_at` | `name`, `code` або `created_at` |
| `order` | `desc` | `asc` або `desc` |
| `name` | | префікс імені, від 3 символів, без урахування регістру |
| `code` | | префікс коду, від 3 символів |
| `created_from` | | `YYYY-MM-DD` або `YYYY-MM-DDTHH:MM:SS`, включно |
| `created_to` | | те саме; дата без часу означає кінець цього дня |

Другим ключем сортування завжди йде `id` у тому самому напрямку.

```bash
curl -s "http://localhost:8080/api/sensors?code=SN-00&sort=name&order=asc&page_size=1" \
  -H "Authorization: Bearer $ACCESS"
```

```json
{
  "items": [
    {"id": 1005, "code": "SN-001005", "name": "CO2 sensor 1005", "type": "co2", "unit": "ppm",
     "device_id": "DEV-00252", "location": "Server room", "status": "active",
     "created_at": "2025-07-03T18:54:05Z", "last_seen_at": "2026-10-11T11:09:05Z"}
  ],
  "total": 5000,
  "page": 1,
  "page_size": 1
}
```

`total` — кількість рядків з урахуванням фільтрів. Секрети датчиків (`secret_enc`, `key_version`) ніколи не повертаються.

`422 {"error":"validation_failed","errors":{"<параметр>":"<повідомлення>"}}` — будь-який параметр невалідний (усі помилки повертаються разом).

### `GET /api/sensors/{id}/readings` — сирі показання

Право: `sensors.readings`. Діапазон часу обовʼязковий, результат відсортований за `ts` за зростанням.

| Параметр | За замовчуванням | Правила |
|---|---|---|
| `from` | обовʼязковий | `YYYY-MM-DD` або `YYYY-MM-DDTHH:MM:SS`, включно |
| `to` | обовʼязковий | те саме; дата без часу означає кінець цього дня |
| `page` | `1` | ціле ≥ 1 |
| `page_size` | `100` | ціле від 1 до 1000 |

```bash
curl -s "http://localhost:8080/api/sensors/1/readings?from=2026-10-01&to=2026-10-01&page_size=2" \
  -H "Authorization: Bearer $ACCESS"
```

```json
{"items": [{"ts": "2026-10-01T10:00:00Z", "value": 20.0}], "page": 1, "page_size": 2, "has_more": false}
```

(Приклад відповіді, коли таблиця показань існує; без неї відповідь `503`.)

`total` не повертається (таблиця велика), натомість `has_more`.

| Код | Коли |
|---|---|
| 404 `sensor_not_found` | датчика немає |
| 422 | немає `from`/`to`, формат дати, `from` пізніше за `to`, завеликий `page_size` |
| 503 `readings_unavailable` | таблицю `sensor_readings` ще не створено консʼюмерами або вона недоступна |

Таблицю показань створюють консʼюмери; API очікує в ній колонки `sensor_id`, `ts` (DATETIME, UTC), `value` та унікальність `(sensor_id, ts)`.

## Консольні команди

З кореня репозиторію (`PHP="docker compose -f docker/compose.yml exec -T -u $(id -u) php"`):

| Команда | Що робить |
|---|---|
| `$PHP vendor/bin/doctrine-migrations migrate --no-interaction` | міграції схеми й початкових ролей/прав |
| `$PHP bin/console app:superadmin:ensure` | створює суперадміна з `SUPERADMIN_EMAIL` з порожнім паролем, ідемпотентно |
| `$PHP bin/console app:sensors:seed [--count=5000]` | фейкові датчики, ідемпотентно за `code` |
| `$PHP bin/console app:user:create <email> --role=manager\|user [--password=…]` | створює користувача; без `--password` запитує інтерактивно |

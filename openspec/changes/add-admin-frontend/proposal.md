# Proposal

## Why

Дані датчиків і API з'являються в зміні `add-backend-auth-and-sensors-api`, але користувачу їх немає як переглянути: `frontend/` порожній. Потрібна адмінка: вхід, захищений каркас у візуальному стилі проєкту й екран списку датчиків.

## What Changes

- Frontend у `frontend/`: Vite + React + TS + Tailwind + shadcn/ui, TanStack Router/Query/Table, RHF + Zod, lucide-react.
- Дизайн-токени за `.agents/docs/admin-styles/`.
- Клієнт API з одноразовим refresh при 401; refresh у localStorage, access у памʼяті.
- Сторінка логіну, захищені маршрути, layout із сайдбаром-«пігулкою», вихід, навігація й повідомлення про відмову залежно від ролі.
- Екран датчиків: серверні пагінація, сортування, фільтри, стан у URL.
- CSP у nginx і ESLint-заборона `dangerouslySetInnerHTML` (компенсація refresh у localStorage).
- Тести Vitest + Testing Library.

Передумова: розгорнутий стек і API з `add-backend-auth-and-sensors-api` (мінімум розділи Docker, auth, RBAC і `GET /api/sensors`). До його готовності frontend розробляється проти мок-відповідей, а наскрізна перевірка відкладається.

Поза межами: усе в `backend/`; екрани користувачів, сесій і прав; графіки (Recharts лише в залежностях).

## Capabilities

### New Capabilities
- `admin-shell`: сторінка логіну, захищені маршрути, робота з токенами в браузері, сайдбар, стиль.
- `sensor-list-ui`: екран списку датчиків зі станом таблиці в URL.

### Modified Capabilities

(немає)

## Impact

- Новий каталог `frontend/`; у `docker/nginx/default.conf` лише CSP-заголовки; `CLAUDE.md` доповнюється командами frontend.
- Споживає `POST /token`, logout, `GET /api/sensors`.
- Залежності: React, TanStack Router/Query/Table, RHF, Zod, shadcn/ui, Recharts, lucide-react, Vitest, ESLint.

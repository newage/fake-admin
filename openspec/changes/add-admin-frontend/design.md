# Design

## Context

Backend і Docker-стек з'являються в `add-backend-auth-and-sensors-api`; контракт API описаний там (`docs/api.md`). Один origin через nginx, тож CORS не потрібен. Мотивація — у `proposal.md`, вимоги — у `specs/`. Вимоги й відхилені альтернативи: `.agents/docs/first-decisions.md`.

## Goals / Non-Goals

**Goals:**
- Вхід, захищений каркас і екран списку датчиків у стилі `.agents/docs/admin-styles/`.
- Мінімум коду над контрактом API: жодної бізнес-логіки, що дублює backend (права визначає сервер, фронт лише ховає пункти).

**Non-Goals:**
- Екрани користувачів, сесій і прав; графіки; клієнтська фільтрація.

## Decisions

**Стек.** Vite + React + TS, shadcn/ui на Tailwind, TanStack Router (маршрути, `beforeLoad` для захисту, `validateSearch` на Zod для стану таблиці в URL), TanStack Query (запити), TanStack Table (таблиця без клієнтської пагінації й фільтрації: `manualPagination`, `manualSorting`, `manualFiltering`), RHF + Zod (форми).

**Сесія.** Один fetch-клієнт з одноразовим refresh при 401: спільний промис, щоб паралельні запити не оновлювали токен двічі; невдалий refresh очищає сесію й веде на логін. Refresh у localStorage за вибором розробника, access у памʼяті. Альтернатива httpOnly cookie відкинута розробником; ціна — XSS може вкрасти refresh, тому CSP і ESLint-заборона `dangerouslySetInnerHTML`.

**Роль.** Береться з відповіді входу/claims access, лише для показу пунктів навігації; джерело правди — 403 сервера.

**Стиль.** Токени Tailwind/CSS-змінних за `admin-styles/`; shadcn-компоненти кастомізуються токенами, не форками.

**Розробка без backend.** Поки API недоступне, тести й UI працюють проти мок-відповідей (MSW або подібне лише у тестах); наскрізна перевірка після готовності backend.

**Тести.** Vitest + Testing Library: клієнт API, форма логіну, редирект, показ за роллю, схема URL-параметрів, таблиця.

## Risks / Trade-offs

- [XSS краде refresh із localStorage] → CSP, без небезпечного HTML, ротація й відкликання на backend.
- [Контракт API змінюється в backend] → типи відповідей в одному модулі, узгодження з `docs/api.md`.
- [Мок розходиться з реальним API] → наскрізний прохід у розділі закриття.

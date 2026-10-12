---
title: "Пагінувати, сортувати й фільтрувати список датчиків на сервері через OFFSET"
status: proposed
date: 2026-10-11
deciders: [Vadim]
supersedes: []
amends: []
relates-to: []
tags: [sensors, api, pagination, performance]
review-by: 2027-10-11
confidence: medium
---

# Пагінувати, сортувати й фільтрувати список датчиків на сервері через OFFSET

## Context

Адмінка показує список із тисяч датчиків (сидер заводить ≈5 000, очікується до 10 000), і рішення ухвалюється під тиском performance і scalability. Фільтри й сортування мають іти параметрами `GET /api/sensors`, щоб RBAC міг обмежувати їх на сервері. Консʼюмери постійно оновлюють `last_seen_at`, тому віддавати клієнту весь список не можна.

## Decision

Ми пагінуємо, сортуємо й фільтруємо список датчиків на сервері через `LIMIT/OFFSET`, а стан таблиці зберігаємо в URL (TanStack Router). Сортування йде за індексованими `name`, `code`, `created_at`; фільтр по `name` і `code` — точний збіг або префікс від 3 символів, по даті — діапазон. Ми свідомо обираємо простоту замість keyset-пагінації й повертаємось до цього питання, коли датчиків стане понад 10 000.

## Consequences

- Реалізація проста й працює на звичайних індексах MySQL; перехід на довільну сторінку лишається.
- `OFFSET` гальмує на глибоких сторінках; ми приймаємо це до 10 000 датчиків (свідомий компроміс: performance і scalability поступаються простоті).
- Текстовий фільтр лише за префіксом від 3 символів; пошук за підрядком (`%текст%`) не підтримується й потребує окремого рішення.
- Потрібні індекси на `sensors.name`, `sensors.code`, `sensors.created_at`.
- Перехід на keyset-пагінацію коштує medium: складений курсор `(колонка, id)` на кожну колонку сортування, курсор у URL, UI без номерів сторінок.
- Confidence medium: за ≈10 років проблема малоймовірна, далі ймовірні інші рішення, зокрема переписування бекенду.

## Architecture Contract

```adl
system "fake-admin"
  service "api"
    assert sensors-list-paginated: GET /api/sensors завжди пагінується на сервері з обмеженим page size і не повертає весь список
      check fitness_function trigger "PHPUnit у CI" owner "Vadim" severity stop
    assert sensors-text-filter-min-3: текстовий фільтр по name і code приймає точний збіг або префікс від 3 символів, коротший префікс відхиляється
      check fitness_function trigger "PHPUnit у CI" owner "Vadim" severity stop
  service "mysql"
    assert sensors-list-indexed: на sensors.name, sensors.code, sensors.created_at є індекси
      check fitness_function trigger "тест схеми після міграцій у CI" owner "Vadim" severity feedback
    assert sensors-offset-limit: кількість рядків у sensors не перевищує 10000, інакше рішення переглядається (перехід на keyset)
      check manual_review trigger "review-by 2027-10-11 або перевищення 10000" owner "Vadim" severity feedback
```

## Alternatives Considered

- Фільтрація й сортування на клієнті: відкинуто, бо повільний старт і не масштабується на тисячі датчиків.
- Keyset-пагінація (курсор): відкинуто зараз через складний курсор при сортуванні за кількома колонками (`name` не унікальне, потрібен `(колонка, id)`) і відсутність переходу на довільну сторінку. Effort medium; повертаємось до неї при понад 10 000 датчиків.

## Notes

- Author: Vadim
- Approved: —
- Last updated: 2026-10-11

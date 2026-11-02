# ДАРТ: перебудова tourism.gov.ua

Контекст для Claude Code. Мова спілкування із замовником (Дмитро, emuseum): українська.

- Старий сайт: https://www.tourism.gov.ua/ (Державне агентство розвитку туризму України). Блокує ботів (robots), з хмари не відкривається.
- Стек на прохання Дмитра: **Next.js 16** (`web/`, App Router, SSR, standalone) + Laravel 13 + Filament 5 (`api/`: адмінка `/admin`, API `/api/v1`). PostgreSQL у проді, SQLite локально й у тестах. Пов'язаний проєкт: map.tourism.gov.ua (репо emuseum-code/map-dart), звідти взято каркас api/ і деплой.
- Старий сайт зроблено у **Webflow**. Дизайн 1:1: зберігаємо його HTML-розмітку, CSS (`web/public/webflow/`) і скрипти Webflow з їхнього CDN. Адреси ті самі: `/`, `/news-and-announcements`, `/blog/{slug}`, `/orders`, `/orders/{slug}`, `/reports/{slug}`, `/licensuvannya` тощо; параметри пагінації Webflow (`?6f78dda3_page=2`) теж.
- Контент: знімок сайту (зібраний через Chrome Дмитра) → `tools/convert_webflow.py` → `api/database/seed-data/tourism-gov.json` → `php artisan site:import` (запускається в entrypoint, коли знімок змінився; не перезаписує змінене в адмінці, `--force` перезаписує). Сам знімок: `/mnt/project-files/tourism-gov/tourism-gov-export.json.gz`.
- Модель: `Page` (`path` + HTML тіла, скрипти, стилі, `wf_page`), `Post` (новини `/blog/{slug}`), `Category` (news/announcement), `Document` (`kind` order|report), `Setting` (шапка, підвал, віджет доступності, шаблони сторінок записів).
- Динамічні списки в HTML сторінок: `<template data-collection="news|orders|reports" data-filter data-limit data-page-param data-pager>` з одним елементом-шаблоном (`{{url}}`, `{{title}}`, `{{date|d.m.Y}}`, `{{{raw}}}`), рендерить `App\Support\Webflow`.
- `web/`: Next.js лише збирає HTML-документ (`app/[[...path]]/route.ts` + `lib/document.ts`): `/api/v1/layout` + `/api/v1/render?path=`. Пошук `/search?query=`. Англійська `/en/…` (контент ще не перенесено).
- Аватар без фото: кругла плашка з двома літерами (`User::getFilamentAvatarUrl`).
- Перед push: `cd api && vendor/bin/pint && php artisan test`, `cd web && npx tsc --noEmit`.
- Локально: `cd api && php artisan serve` (:8000), `cd web && npm run dev` (:3000, /api проксується на :8000).
- VPS спільний з іншими проєктами: Docker-образи збирати лише на GitHub Actions (`deploy.yml`), не на сервері; деплоїти по одному. Сервіси з префіксом `tg-`, Caddy-блок `/opt/caddy-sites/tourism-gov.caddy`, перед reload `caddy validate`.

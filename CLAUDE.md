# ДАРТ: перебудова tourism.gov.ua

Контекст для Claude Code. Мова спілкування із замовником (Дмитро, emuseum): українська.

- Старий сайт: https://www.tourism.gov.ua/ (Державне агентство розвитку туризму України). Блокує ботів (robots), з хмари не відкривається.
- Стек на прохання Дмитра: **Next.js 16** (`web/`, App Router, SSR, standalone) + Laravel 13 + Filament 5 (`api/`: адмінка `/admin`, API `/api/v1`). PostgreSQL у проді, SQLite локально й у тестах. Пов'язаний проєкт: map.tourism.gov.ua (репо emuseum-code/map-dart), звідти взято каркас api/ і деплой.
- Мови: українська без префікса (`/news`), англійська з `/en` (`/en/news`). `web/proxy.ts` переписує запити в `app/[lang]/…`.
- Модель: `Post` (новини, `/news/{slug}`), `Category`, `Page` (дерево, адреса = `path` батьків + slug, сторінки ловить `app/[lang]/[...path]`), `Document`, `MenuItem` (меню header/footer/top), `Banner`, `Setting`.
- Аватар без фото: кругла плашка з двома літерами (`User::getFilamentAvatarUrl`).
- Перед push: `cd api && vendor/bin/pint && php artisan test`, `cd web && npx tsc --noEmit`.
- Локально: `cd api && php artisan serve` (:8000), `cd web && npm run dev` (:3000, /api проксується на :8000).
- VPS спільний з іншими проєктами: Docker-образи збирати лише на GitHub Actions (`deploy.yml`), не на сервері; деплоїти по одному. Сервіси з префіксом `tg-`, Caddy-блок `/opt/caddy-sites/tourism-gov.caddy`, перед reload `caddy validate`.

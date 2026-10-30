# tourism.gov.ua на Next.js + Laravel

- `web/`: публічний сайт, Next.js 16 (SSR).
- `api/`: Laravel 13 + Filament 5, адмінка `/admin` і API `/api/v1` (layout, home, news, news/{slug}, pages?path=, documents, search, sitemap).
- `deploy/`, `docker-compose*.yml`, `.github/workflows/deploy.yml`: деплой на спільний VPS (образи збирає GitHub Actions).

Локально:

```sh
cd api && composer install && cp .env.example .env && php artisan key:generate && touch database/database.sqlite && php artisan migrate && php artisan serve
cd web && npm ci && npm run dev   # http://localhost:3000
```

Docker: `cp api/.env.docker.example api/.env && docker compose up -d --build`, сайт на http://localhost:8095.

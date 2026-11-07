// pm2 на сервері Jam (78.27.234.124): тримає Next.js (standalone) запущеним і перезапускає після падіння.
// НАЗВА, ПОРТ і ПОРТ API мають збігатися з тими, що передавали в server/add-dart.sh.
const DOMAIN = process.env.SITE_DOMAIN || 'dart.discover.ua'
const API_PORT = process.env.API_PORT || 8115
module.exports = {
  apps: [
    {
      name: 'dart',
      cwd: __dirname + '/web',
      script: 'server.js',
      env: {
        HOSTNAME: '127.0.0.1',
        PORT: 3015,
        NODE_ENV: 'production',
        NEXT_TELEMETRY_DISABLED: '1',
        // SSR ходить в API через внутрішній вхід nginx (127.0.0.1:API_PORT), не через публічний домен
        API_INTERNAL: `http://127.0.0.1:${API_PORT}/api`,
        NEXT_PUBLIC_API_BASE: `https://${DOMAIN}/api`,
        SITE_URL: `https://${DOMAIN}`,
        // 'true' лише на бойовому домені; інакше noindex
        INDEXABLE: process.env.INDEXABLE || 'auto',
      },
      max_memory_restart: '512M',
    },
  ],
}

#!/usr/bin/env bash
# tourism.gov.ua (ДАРТ) на сервері Jam (78.27.234.124: nginx + pm2 + PHP 8.4-FPM + MySQL 8, без Docker).
# Варіант add-site.sh шаблону Jam (як server/add-zpcultura.sh у zpcultura): Next.js web/ + Laravel api/ (на сервері backend/).
#   - nginx віддає Laravel: /admin, /api, /storage, /filament, /livewire*, /vendor, /up, /logo.svg, /logo-white.svg; решту — Next.js;
#   - внутрішній вхід API для SSR Next.js на 127.0.0.1:<порт API> (як tg-web:8081 у Docker): Next.js не ходить через
#     публічний домен, тож сайт працює й перевіряється ще до перемикання DNS;
#   - --no-ssl: не видавати сертифікат (поки DNS домену веде на старий сервер); після перемикання DNS запустити ще раз без --no-ssl.
#
# Запуск (від root):
#   bash add-dart.sh <назва> <домен> <порт Next.js> "<публічний ключ GitHub Actions>" [--api-port N] [--no-ssl] [--public]
# Приклад:
#   bash add-dart.sh dart dart.discover.ua 3015 "ssh-ed25519 AAAA... gh-actions-jam" --api-port 8115 --no-ssl
#
# <назва>    — латиницею: з неї будуються /var/www/<назва>, ім'я в pm2, пул PHP, база
# <порт>     — вільний внутрішній порт Node, той самий, що в ecosystem.config.cjs
# --public   — НЕ ховати від пошукових систем. Без нього сайт тестовий: noindex на всіх відповідях
#
# Повторний запуск для того самого сайту безпечний: .env, база й сертифікат не перезаписуються.

set -euo pipefail

NAME="${1:?Вкажіть назву сайту першим аргументом}"
DOMAIN="${2:?Вкажіть домен другим аргументом}"
PORT="${3:?Вкажіть порт третім аргументом}"
DEPLOY_PUBKEY="${4:?Вкажіть публічний ключ GitHub Actions четвертим аргументом}"
shift 4
PUBLIC=false
SSL=true
API_PORT=8115
while [ $# -gt 0 ]; do
  case "$1" in
    --public) PUBLIC=true ;;
    --no-ssl) SSL=false ;;
    --api-port) API_PORT="${2:?}"; shift ;;
    *) echo "Невідомий параметр: $1"; exit 1 ;;
  esac
  shift
done

APP_DIR="/var/www/$NAME"
BACKEND_DIR="$APP_DIR/backend"
PHP=8.4
POOL="/etc/php/$PHP/fpm/pool.d/$NAME.conf"
SOCK="/run/php/$NAME.sock"
DB="${NAME//-/_}"
SITE_CONF="/etc/nginx/sites-available/$NAME"

echo "==> 0. Перевірка аргументів"
if ! [[ "$NAME" =~ ^[a-z0-9-]+$ ]]; then
  echo "   Назва має складатися з малих латинських літер, цифр і дефісів: $NAME"
  exit 1
fi
if ! [[ "$PORT" =~ ^[0-9]+$ ]] || [ "$PORT" -lt 3000 ] || [ "$PORT" -gt 3999 ]; then
  echo "   Порт має бути числом від 3000 до 3999: $PORT"
  exit 1
fi
# Назва вже зайнята іншим доменом — не перезаписуємо чужий сайт
if [ -f "$SITE_CONF" ] && ! grep -qE "server_name[^;]*[[:space:]]$DOMAIN[[:space:];]" "$SITE_CONF"; then
  echo "   Назва «$NAME» уже використовується іншим сайтом:"
  grep -m1 "server_name" "$SITE_CONF"
  echo "   Оберіть іншу назву."
  exit 1
fi
if ! command -v "php-fpm$PHP" >/dev/null; then
  echo "   Немає PHP $PHP. Сервер налаштовано не через server-setup.sh цього скіла — доставте PHP $PHP, MySQL і Composer."
  exit 1
fi

echo "==> 1. Перевірка, що порт $PORT не зайнятий іншим сайтом"
if grep -rqs "proxy_pass http://127.0.0.1:$PORT;" /etc/nginx/sites-available \
  && ! grep -qs "proxy_pass http://127.0.0.1:$PORT;" "$SITE_CONF"; then
  echo "   Порт $PORT уже використовує інший сайт:"
  grep -rl "proxy_pass http://127.0.0.1:$PORT;" /etc/nginx/sites-available
  exit 1
fi

# Порт може слухати pm2 іншого сайту, якого nginx ще не знає (або ще не перезапущений після переїзду)
if ss -ltnH "( sport = :$PORT )" | grep -q . && ! sudo -u deploy pm2 jlist 2>/dev/null | grep -q "\"name\":\"$NAME\""; then
  echo "   На порту $PORT уже щось слухає:"; ss -ltnp "( sport = :$PORT )"; exit 1
fi
if ss -ltnH "( sport = :$API_PORT )" | grep -q . && ! grep -qs "listen 127.0.0.1:$API_PORT;" "$SITE_CONF"; then
  echo "   Порт API $API_PORT уже зайнятий:"; ss -ltnp "( sport = :$API_PORT )"; exit 1
fi

echo "==> 2. Папка сайту $APP_DIR"
install -d -o deploy -g deploy "$APP_DIR"

echo "==> 3. Ключ GitHub Actions — щоб репозиторій міг деплоїти"
grep -qxF "$DEPLOY_PUBKEY" /home/deploy/.ssh/authorized_keys \
  || echo "$DEPLOY_PUBKEY" >> /home/deploy/.ssh/authorized_keys

echo "==> 4. PHP-FPM: окремий пул $NAME від користувача deploy"
# Той самий користувач, що й автодеплой, — тоді немає проблем із правами на storage/.
# ondemand: процеси PHP запускаються лише під запити — кілька сайтів на сервері не з'їдають пам'ять
cat > "$POOL" <<CONF
[$NAME]
user = deploy
group = deploy
listen = $SOCK
listen.owner = www-data
listen.group = www-data
listen.mode = 0660
pm = ondemand
pm.max_children = 8
pm.process_idle_timeout = 30s
pm.max_requests = 500
php_admin_value[upload_max_filesize] = 64M
php_admin_value[post_max_size] = 64M
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 120
CONF
# Стандартний пул www не потрібен, коли є пули сайтів
if [ -f "/etc/php/$PHP/fpm/pool.d/www.conf" ]; then
  mv "/etc/php/$PHP/fpm/pool.d/www.conf" "/etc/php/$PHP/fpm/pool.d/www.conf.disabled"
fi
"php-fpm$PHP" -t
# reload, а не restart: новий пул підхоплюється, а пули інших сайтів не обривають запити
systemctl reload "php$PHP-fpm"

echo "==> 5. Папки backend, база MySQL «$DB» і backend/.env"
install -d -o deploy -g deploy "$APP_DIR/frontend" "$BACKEND_DIR"
for D in storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions \
         storage/framework/views storage/logs bootstrap/cache; do
  install -d -o deploy -g deploy "$BACKEND_DIR/$D"
done
# install -d віддає deploy лише кінцеву папку; проміжні (storage, storage/app, bootstrap…) лишаються root,
# і rsync деплою не може записати bootstrap/app.php
chown -R deploy:deploy "$BACKEND_DIR/storage" "$BACKEND_DIR/bootstrap"
if [ ! -f "$BACKEND_DIR/.env" ]; then
  # Паролі генеруються тут і нікуди не виводяться — лише в .env
  DBPASS="$(openssl rand -base64 33 | tr -dc A-Za-z0-9 | head -c 32)"
  mysql <<SQL
CREATE DATABASE IF NOT EXISTS \`$DB\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB'@'localhost' IDENTIFIED BY '$DBPASS';
ALTER USER '$DB'@'localhost' IDENTIFIED BY '$DBPASS';
GRANT ALL PRIVILEGES ON \`$DB\`.* TO '$DB'@'localhost';
FLUSH PRIVILEGES;
SQL
  cat > "$BACKEND_DIR/.env" <<ENV
APP_NAME="$NAME"
APP_ENV=production
APP_KEY=base64:$(openssl rand -base64 32)
APP_DEBUG=false
APP_URL=https://$DOMAIN
FRONTEND_URL=https://$DOMAIN

APP_LOCALE=uk
APP_FALLBACK_LOCALE=uk
APP_FAKER_LOCALE=uk_UA

APP_MAINTENANCE_DRIVER=file

BCRYPT_ROUNDS=12

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_DAILY_DAYS=14
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=$DB
DB_USERNAME=$DB
DB_PASSWORD=$DBPASS

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=true

BROADCAST_CONNECTION=log
FILESYSTEM_DISK=local
QUEUE_CONNECTION=sync
CACHE_STORE=database

# Пошта: поки log — листи лише в лог. Справжній SMTP налаштовується, коли є скринька
MAIL_MAILER=log
MAIL_FROM_ADDRESS="noreply@${DOMAIN#new.}"
MAIL_FROM_NAME="$NAME"
ENV
  chown deploy:deploy "$BACKEND_DIR/.env"
  chmod 600 "$BACKEND_DIR/.env"
else
  echo "   .env уже є — не чіпаю"
fi

# Планувальник Laravel сайту (щоденні завдання: очищення кошика model:prune, журналів тощо) — cron користувача deploy
CRON_LINE="* * * * * cd $BACKEND_DIR && php artisan schedule:run >> /dev/null 2>&1"
( crontab -u deploy -l 2>/dev/null | grep -vF "$BACKEND_DIR && php artisan schedule:run" || true; echo "$CRON_LINE" ) | crontab -u deploy -  # || true: grep без збігів (перший запуск) дає код 1, і set -e обірвав би дужки до echo

echo "==> 6. nginx: адмінка й API → Laravel, решта → Next.js на порту $PORT"
if $PUBLIC; then
  NOINDEX=""
else
  # Тестовий сайт: noindex на кожній відповіді. robots.txt сканування НЕ забороняє —
  # інакше Google не побачить noindex. Прибрати (перезапустити з --public) лише при переїзді на основний домен.
  NOINDEX='    add_header X-Robots-Tag "noindex, nofollow, noarchive" always;
    location = /robots.txt {
        default_type text/plain;
        return 200 "User-agent: *\nDisallow:\n";
    }
'
fi
PROXY="        proxy_pass http://127.0.0.1:$PORT;
        proxy_http_version 1.1;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection \"upgrade\";"

# Конфіг із сертифікатом (certbot дописує блоки 443) не перезаписуємо — лише показуємо, що змінилося б
TARGET_CONF="$SITE_CONF"
if [ -f "$SITE_CONF" ] && grep -q "managed by Certbot" "$SITE_CONF"; then
  TARGET_CONF="$SITE_CONF.new"
  echo "   $SITE_CONF уже з SSL — новий варіант записано в $TARGET_CONF, перенесіть зміни вручну"
fi

cat > "$TARGET_CONF" <<NGINX
# $DOMAIN
#   Laravel ($BACKEND_DIR/public): /admin, /api, /storage, /filament, /livewire-*, ресурси Filament
#   Next.js (127.0.0.1:$PORT): усе інше
server {
    listen 80;
    server_name $DOMAIN;

    client_max_body_size 64M;
    root $BACKEND_DIR/public;

$NOINDEX
    # Заголовки безпеки (файл створює server-setup.sh)
    include snippets/security-headers.conf;
    # --- Laravel ---
    # Завантажені файли (будь-якого типу медіатеки) і ресурси Filament кешуються браузером
    # (ці блоки мають бути вище за наступний)
    location ~ ^/storage/.+\.[A-Za-z0-9]+\$ {
        try_files \$uri /index.php?\$query_string;
        expires 30d;
        access_log off;
    }

    location ~ ^/(css/filament|js/filament|fonts/filament)/.+\.(css|js|woff2?|ttf|svg)\$ {
        try_files \$uri /index.php?\$query_string;
        expires 30d;
        access_log off;
    }

    location ~ ^/(admin|api|filament|livewire[^/]*|vendor|up|storage|css/filament|js/filament|fonts/filament)(/|\$) {
        try_files \$uri /index.php?\$query_string;
    }

    # Логотипи адмінки лежать у Laravel public/
    location ~ ^/logo(-white)?\.svg\$ {
        try_files \$uri =404;
        expires 7d;
    }

    # Точка входу Laravel; ззовні напряму не відкривається
    location = /index.php {
        internal;
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$SOCK;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
    }

    # --- Next.js ---
    location / {
$PROXY
    }
}

# Внутрішній API для SSR Next.js (лише з цього сервера)
server {
    listen 127.0.0.1:$API_PORT;
    server_name _;
    client_max_body_size 64M;
    root $BACKEND_DIR/public;
    location / { try_files \$uri /index.php?\$query_string; }
    location = /index.php {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:$SOCK;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        fastcgi_param DOCUMENT_ROOT \$realpath_root;
        fastcgi_read_timeout 300;
    }
}
NGINX

if [ "$TARGET_CONF" = "$SITE_CONF" ]; then
  ln -sf "$SITE_CONF" "/etc/nginx/sites-enabled/$NAME"
  nginx -t
  systemctl reload nginx

  if ! $SSL; then
    echo "==> 7. SSL пропущено (--no-ssl). Після перемикання DNS $DOMAIN → цей сервер запустіть скрипт ще раз без --no-ssl."
  else
  echo "==> 7. SSL-сертифікат Let's Encrypt (сам продовжується) і HTTP/2"
  certbot --nginx -d "$DOMAIN" --non-interactive --agree-tos \
    --register-unsafely-without-email --redirect
  sed -i 's/    listen 443 ssl; # managed by Certbot/    listen 443 ssl http2; # managed by Certbot/' "$SITE_CONF"
  nginx -t
  systemctl reload nginx
  fi
fi

echo
echo "Готово. https://$DOMAIN запрацює після першого деплою з GitHub (до того — 502)."
$PUBLIC || echo "Сайт прихований від пошукових систем (noindex). Для бойового домену — запуск з --public."

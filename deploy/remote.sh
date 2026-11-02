#!/bin/sh
# Runs on the server from the GitHub Actions deploy job, in /opt/tourism-gov (same VPS as the museum portal in /opt/mincult).
# Needs DOMAIN; ADMIN_EMAIL / ADMIN_PASSWORD create the administrator on the first run.
set -eu
cd /opt/tourism-gov

command -v docker >/dev/null 2>&1 || { echo "docker is missing on the server"; exit 1; }
free -m

# api/.env is created once and then kept: it holds APP_KEY and the database password.
if [ ! -f api/.env ]; then
  cp api/.env.docker.example api/.env
  KEY="base64:$(head -c 32 /dev/urandom | base64)"
  DBPW="$(head -c 24 /dev/urandom | base64 | tr -dc 'A-Za-z0-9')"
  sed -i "s|^APP_KEY=.*|APP_KEY=$KEY|; s|^DB_PASSWORD=.*|DB_PASSWORD=$DBPW|" api/.env
  printf 'DB_PASSWORD=%s\n' "$DBPW" > .env
fi
setenv() { f="$1"; shift; grep -q "^$1=" "$f" && sed -i "s|^$1=.*|$1=$2|" "$f" || echo "$1=$2" >> "$f"; }
setenv api/.env APP_URL "https://$DOMAIN"
setenv .env SITE_URL "https://$DOMAIN"

# the museum portal must be up: its Caddy holds 80/443 and its network carries our traffic
docker network inspect mincult_default >/dev/null 2>&1 || { echo "mincult_default network is missing: deploy the museum portal first"; exit 1; }

export ADMIN_EMAIL="${ADMIN_EMAIL:-admin@tourism.gov.ua}" ADMIN_PASSWORD="${ADMIN_PASSWORD:-}"
C="docker compose -p tourism-gov -f docker-compose.yml -f docker-compose.prod.yml"
# The deploy job builds the images on GitHub and loads them here (PREBUILT=1): this shared VPS builds nothing.
if [ "${PREBUILT:-0}" != 1 ]; then
  echo "images must be built on GitHub Actions (PREBUILT=1), not on the shared VPS"; exit 1
fi
$C up -d --no-build --remove-orphans
# nginx keeps the old addresses of recreated app/site containers (502); restart it after every deploy
$C restart tg-web

# site block for the museum portal's Caddy, then reload it (no downtime for the other sites)
mkdir -p /opt/caddy-sites
sed "s|TG_DOMAIN|$DOMAIN|" deploy/tourism-gov.caddy > /opt/caddy-sites/tourism-gov.caddy
CADDY="$(docker ps -qf label=com.docker.compose.project=mincult -f label=com.docker.compose.service=caddy | head -1)"
if [ -n "$CADDY" ] && docker exec "$CADDY" test -f /etc/caddy/sites/tourism-gov.caddy; then
  # a broken site block must not take down the other sites behind this Caddy: validate first, roll back on error
  if docker exec "$CADDY" caddy validate --config /etc/caddy/Caddyfile --adapter caddyfile; then
    docker exec "$CADDY" caddy reload --config /etc/caddy/Caddyfile --adapter caddyfile
  else
    rm -f /opt/caddy-sites/tourism-gov.caddy
    echo "::error::caddy validate failed, tourism-gov.caddy removed and Caddy not reloaded"; exit 1
  fi
else
  echo "::warning::Caddy of the museum portal does not mount /opt/caddy-sites; the site is not reachable from outside"
fi

docker image prune -f >/dev/null
$C ps

# smoke test: first the app itself (nginx inside the project), then the public HTTPS through Caddy
inner() { $C exec -T tg-web wget -qO /dev/null -S "http://127.0.0.1$1" 2>&1 | grep -o 'HTTP/1\.[01] [0-9]*' | tail -1 | awk '{print $2+0}'; }
for i in $(seq 1 30); do
  home="$(inner /)"; api="$(inner /api/v1/layout)"; admin="$(inner /admin/login)"
  echo "try $i: / -> $home, /api/v1/layout -> $api, /admin/login -> $admin"
  [ "$home" = 200 ] && [ "$api" = 200 ] && [ "$admin" = 200 ] && break
  sleep 10
done

echo "DNS of $DOMAIN: $(getent hosts "$DOMAIN" | awk '{print $1}' | tr '\n' ' ')(server: $(curl -s --max-time 5 https://api.ipify.org || echo '?'))"
public="$(curl -s --max-time 20 -o /dev/null -w '%{http_code}' "https://$DOMAIN/" || true)"
echo "https://$DOMAIN/ -> $public"
[ "$public" = 200 ] || echo "::warning::https://$DOMAIN/ is not reachable yet: check the DNS A record ($DOMAIN -> this server) and the Caddy log"
$C exec -T tg-app sh -c 'tail -3 storage/logs/import.log 2>/dev/null' || true
[ "$api" = 200 ] || $C exec -T tg-app sh -c 'tail -c 3000 $(ls -t storage/logs/laravel*.log 2>/dev/null | head -1) 2>/dev/null' || true
[ "$home" = 200 ] && [ "$api" = 200 ] && [ "$admin" = 200 ]

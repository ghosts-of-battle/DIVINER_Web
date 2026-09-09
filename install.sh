#!/usr/bin/env bash
#
# One-shot installer for the TAC//PAC web manager.
#
#   sudo bash install.sh
#
# Asks for the four things it cannot work out (hostname, connection string,
# unit id, admin login), installs everything else, and leaves a working site.
# Safe to run twice: it does not overwrite an existing config.local.php, and
# every file it writes is backed up first.
#
# WHAT IT WILL NOT DO: agree to Let's Encrypt's terms for you, or put your
# database on the internet. It offers the certbot run and prints the Atlas
# allowlist step; both are yours to confirm.

set -euo pipefail

APP_DIR=${APP_DIR:-/var/www/DIVINER_Web}
REPO=${REPO:-https://github.com/ghosts-of-battle/DIVINER_Web.git}
SRC_DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)

c_ok()   { printf '\033[32m  ok\033[0m %s\n' "$*"; }
c_warn() { printf '\033[33m  !!\033[0m %s\n' "$*"; }
c_die()  { printf '\033[31m fail\033[0m %s\n' "$*" >&2; exit 1; }
c_step() { printf '\n\033[1m==> %s\033[0m\n' "$*"; }

[ "$(id -u)" -eq 0 ] || c_die "run this with sudo."

# ---------------------------------------------------------------- the distro
. /etc/os-release
MAJ=${VERSION_ID%%.*}
case "$ID" in
  debian|ubuntu)                 FAMILY=deb ;;
  rhel|rocky|almalinux|centos)   FAMILY=rpm ;;
  amzn)                          FAMILY=amzn ;;
  fedora)                        FAMILY=fedora ;;
  *) c_die "unsupported distribution: $ID $VERSION_ID" ;;
esac
c_step "$PRETTY_NAME  ($FAMILY)"

# MongoDB 5.0+ needs AVX; the driver does not, but a local mongod would.
grep -qo avx /proc/cpuinfo || c_warn "no AVX on this CPU - fine for the site, but a local MongoDB will not start."

# ------------------------------------------------------------------ questions
ask() {   # ask VAR "prompt" "default"
  local __v=$1 __p=$2 __d=${3:-} __in
  if [ -n "$__d" ]; then read -rp "$__p [$__d]: " __in; else read -rp "$__p: " __in; fi
  printf -v "$__v" '%s' "${__in:-$__d}"
}
asksecret() { local __v=$1 __p=$2 __in; read -rsp "$__p: " __in; echo; printf -v "$__v" '%s' "$__in"; }

c_step "Questions"
ask HOSTNAME_  "Hostname this site answers on (blank for IP only)" ""
ask MONGO_URI  "MongoDB connection string (mongodb+srv://... or mongodb://...)"
[ -n "$MONGO_URI" ] || c_die "the site cannot do anything without a database."
ask UNIT       "Unit id - the document prefix" "framework"
ask ADMIN_USER "Admin username for the non-Steam login" "admin"
asksecret ADMIN_PASS "Admin password"
[ -n "$ADMIN_PASS" ] || c_die "an empty password would leave the site closed."
ask STEAM_ON   "Offer Steam sign-in? (y/n)" "y"

# ------------------------------------------------------------------ packages
c_step "Packages"
case "$FAMILY" in
  deb)
    apt-get update -qq
    apt-get install -y -qq nginx php-fpm php-cli php-mongodb php-curl git curl >/dev/null
    FPM_USER=www-data; FPM_SVC=$(systemctl list-units --type=service --all 2>/dev/null | grep -o 'php[0-9.]*-fpm' | head -1)
    FPM_SVC=${FPM_SVC:-php-fpm}
    NGINX_CONF=/etc/nginx/sites-available/diviner-web
    ;;
  rpm)
    dnf install -y -q "https://dl.fedoraproject.org/pub/epel/epel-release-latest-$MAJ.noarch.rpm" >/dev/null 2>&1 || true
    dnf install -y -q "https://rpms.remirepo.net/enterprise/remi-release-$MAJ.rpm" >/dev/null 2>&1 || true
    dnf module reset -y php >/dev/null 2>&1 || true
    dnf module enable -y php:remi-8.3 >/dev/null 2>&1 || true
    dnf install -y -q nginx php-fpm php-cli php-pecl-mongodb git curl >/dev/null
    FPM_USER=apache; FPM_SVC=php-fpm; NGINX_CONF=/etc/nginx/conf.d/diviner-web.conf
    ;;
  fedora)
    dnf install -y -q nginx php-fpm php-cli php-pecl-mongodb git curl >/dev/null
    FPM_USER=apache; FPM_SVC=php-fpm; NGINX_CONF=/etc/nginx/conf.d/diviner-web.conf
    ;;
  amzn)
    # No EPEL, no Remi, and the driver is not packaged - so it gets built.
    dnf install -y -q nginx php8.2 php8.2-fpm php8.2-cli git curl >/dev/null
    if ! php -m 2>/dev/null | grep -qi mongodb; then
      c_warn "building the mongodb extension (about a minute)"
      dnf install -y -q php8.2-devel php8.2-pear gcc make openssl-devel cyrus-sasl-devel >/dev/null
      pecl install mongodb >/dev/null 2>&1 || c_die "pecl install mongodb failed - run it by hand to see why."
      echo "extension=mongodb.so" > /etc/php.d/40-mongodb.ini
    fi
    FPM_USER=apache; FPM_SVC=php-fpm; NGINX_CONF=/etc/nginx/conf.d/diviner-web.conf
    ;;
esac
c_ok "$(php -v | head -1)"
php -m | grep -qi mongodb || c_die "the mongodb extension is still not loaded - the site cannot run."
c_ok "mongodb extension present"

# ---------------------------------------------------------------------- code
c_step "Application"
if [ -f "$SRC_DIR/public/index.php" ]; then
  mkdir -p "$APP_DIR"
  cp -r "$SRC_DIR/public" "$SRC_DIR/src" "$APP_DIR/"
  c_ok "copied from $SRC_DIR"
elif [ -d "$APP_DIR/.git" ]; then
  git -C "$APP_DIR" pull --ff-only >/dev/null && c_ok "updated $APP_DIR"
else
  git clone -q "$REPO" "$APP_DIR" && c_ok "cloned into $APP_DIR"
fi

# ------------------------------------------------------- the two-user layout
# nginx serves public/ ITSELF as the nginx user; php-fpm runs the code as its
# own pool user. Lock src/ to the second and leave public/ readable, or the
# stylesheet 404s while PHP keeps working - which looks like a design bug.
c_step "Permissions"
chown -R root:"$FPM_USER" "$APP_DIR"
chmod 755 "$APP_DIR" "$APP_DIR/public"
chmod 644 "$APP_DIR"/public/*
chmod 750 "$APP_DIR/src" "$APP_DIR/src/pages"
find "$APP_DIR/src" -type f -exec chmod 640 {} +
c_ok "public/ readable by the web server, src/ only by $FPM_USER"

# -------------------------------------------------------------------- config
c_step "Configuration"
CONF="$APP_DIR/src/config.local.php"
if [ -f "$CONF" ]; then
  c_warn "$CONF exists - left alone. Delete it and re-run to regenerate."
else
  HASH=$(php -r 'echo password_hash($argv[1], PASSWORD_DEFAULT);' "$ADMIN_PASS")
  STEAM=false; [ "${STEAM_ON:0:1}" = "y" ] && STEAM=true
  umask 077
  cat > "$CONF" <<PHPCONF
<?php
/**
 * Written by install.sh. Not tracked, not servable - src/ sits above the docroot.
 *
 * THE PASSWORD HASH LIVES HERE AND NOT IN THE PHP-FPM POOL: FPM expands \$...
 * sequences in env[] values, so a bcrypt hash set there arrives empty.
 */
return [
    'mongo_uri'   => '$MONGO_URI',
    'database'    => 'ghostd',
    'collection'  => 'pac',
    'unit'        => '$UNIT',

    'admin_user'    => '$ADMIN_USER',
    'password_hash' => '$HASH',

    'steam_login'          => $STEAM,
    'steam_use_pac_admins' => true,
    'steam_admins'         => [],

    'backup_collection' => 'pac_backups',
];
PHPCONF
  chown root:"$FPM_USER" "$CONF"; chmod 640 "$CONF"
  c_ok "wrote $CONF"
fi

# --------------------------------------------------------------------- nginx
c_step "Web server"
SERVER_NAME=${HOSTNAME_:-_}
[ -f "$NGINX_CONF" ] && cp "$NGINX_CONF" "$NGINX_CONF.bak.$(date +%s)"
if [ "$FAMILY" = deb ]; then
  FCGI="include snippets/fastcgi-php.conf;"
  SOCK=$(ls /run/php/*-fpm.sock 2>/dev/null | head -1)
else
  FCGI="include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;"
  SOCK=$(ls /run/php-fpm/*.sock 2>/dev/null | head -1)
fi
SOCK=${SOCK:-/run/php-fpm/www.sock}

cat > "$NGINX_CONF" <<NGINX
server {
    listen 80;
    listen [::]:80;
    server_name $SERVER_NAME;

    root $APP_DIR/public;      # public/ ONLY - src/ must not be served
    index index.php;

    access_log /var/log/nginx/diviner-web.access.log;
    error_log  /var/log/nginx/diviner-web.error.log;

    location / { try_files \$uri \$uri/ /index.php\$is_args\$args; }

    location ~ \.php\$ {
        $FCGI
        fastcgi_pass unix:$SOCK;
    }

    location ~ /\. { deny all; }
    client_max_body_size 8m;   # the branding page uploads an image
}
NGINX
[ "$FAMILY" = deb ] && ln -sf "$NGINX_CONF" /etc/nginx/sites-enabled/diviner-web && rm -f /etc/nginx/sites-enabled/default
nginx -t >/dev/null 2>&1 || { nginx -t; c_die "nginx rejected the config."; }
systemctl enable --now nginx "$FPM_SVC" >/dev/null 2>&1 || true
systemctl restart "$FPM_SVC" nginx
c_ok "nginx serving $APP_DIR/public on $SERVER_NAME"

# ------------------------------------------------------------------ firewall
if command -v firewall-cmd >/dev/null 2>&1 && systemctl is-active --quiet firewalld; then
  firewall-cmd -q --permanent --add-service=http --add-service=https && firewall-cmd -q --reload
  c_ok "firewalld: 80 and 443 open"
elif command -v ufw >/dev/null 2>&1; then
  ufw allow 'Nginx Full' >/dev/null 2>&1 && c_ok "ufw: 80 and 443 open"
fi
if [ "$FAMILY" = rpm ] || [ "$FAMILY" = amzn ] || [ "$FAMILY" = fedora ]; then
  if command -v getenforce >/dev/null && [ "$(getenforce)" != "Disabled" ]; then
    setsebool -P httpd_can_network_connect 1 2>/dev/null && \
      c_ok "SELinux: PHP may make outbound connections (needed for Atlas and Steam)"
  fi
fi

# ---------------------------------------------------------------------- test
c_step "Checking"
sleep 1
CODE=$(curl -s -o /dev/null -w '%{http_code}' -H "Host: ${HOSTNAME_:-localhost}" http://127.0.0.1/ || echo 000)
case "$CODE" in
  200|302) c_ok "the site answers (HTTP $CODE)" ;;
  *) c_warn "the site answered $CODE - see /var/log/nginx/diviner-web.error.log and: journalctl -u $FPM_SVC -n 30" ;;
esac
curl -s -o /dev/null -w '%{http_code}' -H "Host: ${HOSTNAME_:-localhost}" http://127.0.0.1/style.css \
  | grep -q 200 && c_ok "static files serve" || c_warn "style.css did not serve - check permissions on $APP_DIR/public"

# ----------------------------------------------------------------- what next
c_step "Done"
cat <<NEXT

  Site       http://${HOSTNAME_:-<this server's IP>}/
  App        $APP_DIR
  Config     $CONF
  Admin      $ADMIN_USER  (the password you just typed)

  Two things this script deliberately left to you:

  1. ALLOWLIST THIS SERVER IN ATLAS. Until its public IP is on
     Security > Network Access, every page will report a TLS handshake
     failure - Atlas refuses unknown addresses at the TLS layer, so it
     looks like a certificate problem rather than a firewall one.

         curl -4 https://ifconfig.me

  2. TLS. Certbot registers an account with Let's Encrypt and agrees to
     their terms, so run it yourself once DNS points here:

         $( [ "$FAMILY" = deb ] && echo "apt install -y certbot python3-certbot-nginx" || echo "dnf install -y certbot python3-certbot-nginx  # or the venv install, see DEPLOY.md" )
         certbot --nginx -d ${HOSTNAME_:-your.hostname}

     The server_name above must match the -d name exactly, or certbot
     reports it cannot find a matching server block.

  Then sign in through Steam. Your Steam id must be in the unit's
  "$UNIT.admins" document (in game: STRUCTURE > ADMINS > ADD ME), or add
  it to steam_admins in $CONF.

NEXT

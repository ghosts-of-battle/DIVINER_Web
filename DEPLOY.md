# Deploying DIVINER_Web on Linux

Two tracks: **Debian family** (Debian 12+, Ubuntu 22.04+) and **Red Hat
family** (RHEL / Rocky / AlmaLinux 9+, Fedora). The application is the same on
both - PHP files served by nginx or Apache, no Composer, no build step, no
daemon of its own. What differs is package names, the PHP-FPM socket path, and
SELinux.

Read [README.md](README.md) first for what the site is. This file is the
install runbook.

## Before you start

You need four things. Have them written down before touching the server:

| | |
|---|---|
| **A connection string** | `mongodb+srv://...` from Atlas, or `mongodb://...` for a database you host yourself - see [MONGODB.md](MONGODB.md) |
| **A password** | one shared password for the site; you will hash it in step 3 |
| **The unit id** | `framework` for `frameworkmongo.Stratis` - the document prefix |
| **A hostname** | `pac.example.org`, with DNS already pointing at the box |

Requirements: **PHP 8.0 or newer** (the code uses `str_starts_with`), the PECL
`mongodb` extension, and a web server. 512 MB of RAM is plenty.

Everything below is run as root (`sudo -i`, or prefix each command with
`sudo`).

---

## 1. Install packages

### Debian / Ubuntu

```bash
apt update
apt install -y nginx php-fpm php-cli php-mongodb
```

`php-fpm` pulls the distro's default PHP (8.2 on Debian 12, 8.1 on Ubuntu
22.04, 8.3 on Ubuntu 24.04) - all above the 8.0 floor. On Ubuntu, `php-mongodb`
is in **universe**; enable it if the install cannot find the package:

```bash
add-apt-repository universe && apt update
```

For Apache instead of nginx: `apt install -y apache2 php-fpm php-cli php-mongodb`.

### Red Hat family - one block that works on all of them

`dnf install php-pecl-mongodb` fails on RHEL, Rocky, Alma and Amazon Linux -
none of them carry it. Paste this instead; it detects the distribution and
takes the right route:

```bash
. /etc/os-release; maj=${VERSION_ID%%.*}
case "$ID" in
  rhel|rocky|almalinux|centos)
    # Remi packages the driver, and needs EPEL. The URL form works on all three.
    dnf install -y "https://dl.fedoraproject.org/pub/epel/epel-release-latest-$maj.noarch.rpm"
    dnf install -y "https://rpms.remirepo.net/enterprise/remi-release-$maj.rpm"
    dnf module reset php -y && dnf module enable php:remi-8.3 -y
    dnf install -y nginx php-fpm php-cli php-pecl-mongodb
    ;;
  amzn)
    # No modules, no EPEL, no Remi. Build it - about a minute.
    dnf install -y nginx php8.2 php8.2-fpm php8.2-cli \
                   php8.2-devel php8.2-pear gcc make openssl-devel cyrus-sasl-devel
    pecl install mongodb
    echo "extension=mongodb.so" > /etc/php.d/40-mongodb.ini
    ;;
  fedora)
    dnf install -y nginx php-fpm php-cli php-pecl-mongodb
    ;;
  *) echo "Unknown: $ID $VERSION_ID - see the per-distribution notes below" ;;
esac

systemctl restart php-fpm 2>/dev/null
php -m | grep -i mongodb || echo "STILL MISSING - see Building the extension"
```

On **RHEL** with a subscription, enable CodeReady Builder first or some EPEL
dependencies will not resolve:

```bash
subscription-manager repos --enable "codeready-builder-for-rhel-9-$(arch)-rpms"
```

The rest of this section is the same thing explained per distribution.

### Red Hat family - identify the distribution first

`dnf` is not one distribution, and the differences matter here. Run this before
installing anything:

```bash
. /etc/os-release && echo "$ID $VERSION_ID"
dnf module list php 2>/dev/null | head -20
```

**`epel-release` is only a package on Rocky and Alma** (in `extras`). On RHEL
itself it installs from a URL, and Amazon Linux has no EPEL at all - so
`No match for argument: epel-release` tells you which branch you are on, it is
not a broken mirror.

**The `mongodb` extension is packaged only by Fedora and by Remi.** RHEL,
Rocky, Alma and Amazon Linux do not ship it in any repository, EPEL included.
On those you either add Remi or build it - see "Building the extension" below.
`No match for argument: php-pecl-mongodb` is expected there.

#### Rocky / AlmaLinux 9

```bash
dnf module reset php -y && dnf module enable php:8.2 -y
dnf install -y nginx php-fpm php-cli
dnf install -y epel-release          # exists here, in extras
dnf install -y php-pecl-mongodb || echo "not in EPEL - use Remi or build it"
```

#### RHEL 9 (subscribed)

EPEL needs CodeReady Builder enabled and installs from Fedora's URL:

```bash
subscription-manager repos --enable "codeready-builder-for-rhel-9-$(arch)-rpms"
dnf install -y https://dl.fedoraproject.org/pub/epel/epel-release-latest-9.noarch.rpm
```

That still will not have the driver. Remi is the packaged route:

```bash
dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
dnf module reset php -y && dnf module enable php:remi-8.3 -y
dnf install -y nginx php-fpm php-cli php-pecl-mongodb
```

On **RHEL 8** use `epel-release-latest-8.noarch.rpm` and `remi-release-8.rpm`,
and note the default stream is PHP 7.2 - you *must* move to 8.x or the site
will not run.

#### Amazon Linux 2023

No modules, no EPEL, and packages are version-prefixed. The driver must be
built:

```bash
dnf install -y nginx php8.2 php8.2-fpm php8.2-cli
# then: Building the extension, below
```

#### Fedora

The only one where it is a single command:

```bash
dnf install -y nginx php-fpm php-cli php-pecl-mongodb
```

For Apache instead of nginx, swap `nginx` for `httpd`.

### Building the extension

This is the **normal path on RHEL, Rocky, Alma and Amazon Linux** unless you
added Remi - not an exotic fallback. It takes about a minute and needs a
compiler on the box.

```bash
# Debian / Ubuntu (only if the distro package is missing)
apt install -y php-dev php-pear build-essential libssl-dev pkg-config

# RHEL / Rocky / Alma
dnf install -y php-devel php-pear gcc make openssl-devel cyrus-sasl-devel

# Amazon Linux 2023 - version-prefixed
dnf install -y php8.2-devel php8.2-pear gcc make openssl-devel cyrus-sasl-devel

pecl install mongodb
```

If `pecl` reports the extension needs a newer PHP than you have, pin a release
that supports yours rather than upgrading in a hurry - the 1.x line covers
PHP 7.4 through 8.x:

```bash
pecl install mongodb-1.20.1
```

Then load it. `pecl` does not always wire the extension in:

```bash
# Debian / Ubuntu - one file, symlinked into each SAPI
echo "extension=mongodb.so" > /etc/php/$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')/mods-available/mongodb.ini
phpenmod mongodb

# Red Hat family and Amazon Linux
echo "extension=mongodb.so" > /etc/php.d/40-mongodb.ini
systemctl restart php-fpm
```

### Verify before going further

```bash
php -v                     # must be 8.0+
php -m | grep -i mongodb   # must print: mongodb
php --ri mongodb | head     # driver version, libmongoc, TLS library
```

If `php -m` does not list `mongodb`, stop and fix that now - every page of the
site will 500 without it. Note that the **CLI and FPM read different ini
sets**; step 6's smoke test is what proves FPM has it too.

---

## 2. Put the code on the server

```bash
install -d -m 0755 /var/www
git clone https://github.com/ghosts-of-battle/DIVINER_Web.git /var/www/DIVINER_Web
```

Or from your workstation, if the box has no access to the repository:

```bash
rsync -av --exclude '.git' --exclude 'src/config.local.php' \
      ./DIVINER_Web/ root@pac.example.org:/var/www/DIVINER_Web/
```

Ownership - the web user only needs to **read** the code. It never writes to
its own directory:

**Two different users are involved, and this is where deployments break.**
nginx serves `public/` *itself* - the stylesheet, any image - as the **nginx**
user. PHP-FPM runs `index.php` as its own pool user, which on the Red Hat
family is **apache**, not nginx. So `public/` must be readable by the web
server, while `src/` only ever needs the FPM user:

```bash
# Group-own everything by the FPM pool user (check it: grep '^user' in the pool)
chown -R root:apache /var/www/DIVINER_Web        # root:www-data on Debian/Ubuntu

# The docroot: nginx must be able to traverse and read it
chmod 755 /var/www/DIVINER_Web /var/www/DIVINER_Web/public
chmod 644 /var/www/DIVINER_Web/public/*

# src/ holds the database password - FPM only, never the web server
chmod 750 /var/www/DIVINER_Web/src /var/www/DIVINER_Web/src/pages
find /var/www/DIVINER_Web/src -type f -exec chmod 640 {} \;

# The media folder is the ONE place the app writes. Group apache is PHP-FPM;
# nginx is not in that group, which is what makes a "site only" file site only.
mkdir -p /var/www/DIVINER_Web/media
chown root:apache /var/www/DIVINER_Web/media
chmod 770 /var/www/DIVINER_Web/media
```

Shared files live in `media/`, **not** under `public/`. Nothing in there is
served by the web server: every read goes through `?page=file`, which checks
the file's visibility first (`public` = anyone with the link, `site` = signed
in). Check it with `sudo -u nginx ls .../media` (must fail) and
`sudo -u apache ls .../media` (must work).

Do **not** blanket the tree with `chmod -R o=`: on RHEL that locks the nginx
user out of the docroot, every static file falls through `try_files` to
`index.php`, and the site renders with no stylesheet at all while PHP keeps
working perfectly. Check it with `sudo -u nginx cat .../public/style.css`
(must work) and `sudo -u nginx cat .../src/config.local.php` (must not).

`/var/www` already carries the `httpd_sys_content_t` SELinux label, which is
why the path is worth keeping. If you install somewhere else, see step 5.

---

## 3. Configure

Generate the password hash on the server (quote it with **single** quotes
everywhere - the hash contains `$`, which a shell or an nginx config will
otherwise eat):

```bash
php -r "echo password_hash('your password', PASSWORD_DEFAULT), PHP_EOL;"
```

Then pick one of the two forms. **The environment wins over the file.**

### Form A - the config file (recommended; fewest moving parts)

```bash
cp /var/www/DIVINER_Web/src/config.local.example.php \
   /var/www/DIVINER_Web/src/config.local.php
$EDITOR /var/www/DIVINER_Web/src/config.local.php
```

Fill in `mongo_uri`, `unit` and `password_hash`. Then lock it down - this file
holds the database password:

```bash
chown root:www-data /var/www/DIVINER_Web/src/config.local.php   # root:apache on RHEL
chmod 640 /var/www/DIVINER_Web/src/config.local.php
```

The file is gitignored, and `src/` is outside the document root, so it is not
servable even if the permissions slip.

### Sign in through Steam, instead of the password

The site can authenticate against Steam and authorise against PAC's own
`<unit>.admins` document - no second list of people. In `config.local.php`:

```php
'steam_login' => true,
```

or in the environment: `GHOSTD_STEAM=1`, with `GHOSTD_STEAM_ADMINS` for extra
ids and `GHOSTD_STEAM_PAC_ADMINS=0` to ignore the mod's list.

Three things to get right:

- **Set `password_hash` as well, at least at first.** Steam sign-in reads the
  admin list from the database; if the database is down, nobody gets in.
- **The site must know its own URL.** Steam is told where to return, and the
  returned assertion is rejected if it does not match. Behind a proxy that
  terminates TLS, set `base_url` (or `GHOSTD_BASE_URL`) to the public address -
  `https://pac.example.org` - or the login fails with "Steam did not confirm".
- **Outbound HTTPS to `steamcommunity.com`** must work from the web server. On
  the Red Hat family that is the same `httpd_can_network_connect` boolean the
  database needs.

Whoever is on the in-game admin list can then sign in, and `STRUCTURE > ADMINS
> ADD ME` in game is how somebody new is added. See "Signing in" in
[README.md](README.md).

### Form B - the PHP-FPM pool environment (nothing app-side on disk)

Add to the pool config - `/etc/php/8.2/fpm/pool.d/www.conf` on Debian/Ubuntu,
`/etc/php-fpm.d/www.conf` on RHEL:

```ini
env[GHOSTD_MONGO] = mongodb+srv://user:password@cluster.example.mongodb.net/
env[GHOSTD_WEB_PASSWORD_HASH] = $2y$10$....
env[GHOSTD_UNIT] = framework
```

`chmod 640` that pool file too, and restart FPM after editing it. This form
also survives a `git pull` with no merge conflict.

**Never put the password hash here.** PHP-FPM expands `$...` sequences in
`env[]` values, so a bcrypt hash - which is nothing but `$` separated fields -
arrives at PHP as an **empty string**, and the site reports itself
unconfigured while the pool file plainly shows a correct hash. `GHOSTD_MONGO`
and `GHOSTD_UNIT` are safe here because they contain no dollar signs. Put
`password_hash` in `src/config.local.php`, where PHP's single quotes protect
it (Form A). Also watch for a **trailing space** after any value: it becomes
part of the string, and a 61-character bcrypt hash never verifies.

Do **not** rely on `Environment=` in a systemd drop-in: PHP-FPM ships
`clear_env = yes`, which wipes the inherited environment. Pool `env[...]` lines
are the supported route.

(The README's `fastcgi_param` / `SetEnv` form works too - PHP's `getenv()` reads
FastCGI request parameters - but it puts the Atlas password in the web server
config, which is world-readable on most distributions.)

---

## 4. Web server

Only `public/` is ever served. `src/` sits one level above the document root on
purpose. The site routes on `?page=`, so no rewrite rules are strictly needed -
`index.php` as the index is enough.

Find your FPM socket first; the path differs per distro and per PHP version:

```bash
ls /run/php/*.sock /run/php-fpm/*.sock 2>/dev/null
```

### nginx - Debian / Ubuntu

`/etc/nginx/sites-available/diviner-web`:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name pac.example.org;

    root /var/www/DIVINER_Web/public;    # public/ ONLY
    index index.php;

    access_log /var/log/nginx/diviner-web.access.log;
    error_log  /var/log/nginx/diviner-web.error.log;

    location / { try_files $uri $uri/ /index.php$is_args$args; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;   # match the socket you found
    }

    location ~ /\. { deny all; }         # dotfiles, .git if it ever lands here
    client_max_body_size 4m;             # the JSON editor posts whole documents
}
```

```bash
ln -s /etc/nginx/sites-available/diviner-web /etc/nginx/sites-enabled/
rm -f /etc/nginx/sites-enabled/default
nginx -t && systemctl enable --now nginx php8.2-fpm
```

### nginx - RHEL family

There is no `sites-enabled` and no `snippets/fastcgi-php.conf`. Drop the file
in `/etc/nginx/conf.d/diviner-web.conf` and spell the params out:

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name pac.example.org;

    root /var/www/DIVINER_Web/public;
    index index.php;

    location / { try_files $uri $uri/ /index.php$is_args$args; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_pass unix:/run/php-fpm/www.sock;
    }

    location ~ /\. { deny all; }
    client_max_body_size 4m;
}
```

```bash
nginx -t && systemctl enable --now nginx php-fpm
```

The stock RHEL pool already has `listen.acl_users = apache,nginx`, so nginx can
reach the socket without changing the pool user.

### Apache - Debian / Ubuntu

```bash
apt install -y apache2 libapache2-mod-fcgid
a2enmod proxy_fcgi setenvif rewrite
a2enconf php8.2-fpm
```

`/etc/apache2/sites-available/diviner-web.conf`:

```apache
<VirtualHost *:80>
    ServerName pac.example.org
    DocumentRoot /var/www/DIVINER_Web/public

    <Directory /var/www/DIVINER_Web/public>
        AllowOverride None
        Require all granted
        DirectoryIndex index.php
        Options -Indexes
    </Directory>

    ErrorLog  ${APACHE_LOG_DIR}/diviner-web.error.log
    CustomLog ${APACHE_LOG_DIR}/diviner-web.access.log combined
</VirtualHost>
```

```bash
a2dissite 000-default && a2ensite diviner-web
apachectl configtest && systemctl reload apache2
```

### Apache - RHEL family

`httpd` is already wired to php-fpm by `/etc/httpd/conf.d/php.conf`; you only
add the vhost, in `/etc/httpd/conf.d/diviner-web.conf`, with the same body as
above minus the `${APACHE_LOG_DIR}` variables (use `/var/log/httpd/...`).

```bash
systemctl enable --now httpd php-fpm
apachectl configtest && systemctl reload httpd
```

---

## 5. Firewall, SELinux, TLS

### Firewall

```bash
# Debian / Ubuntu
ufw allow 'Nginx Full'      # or 'Apache Full'

# RHEL family
firewall-cmd --permanent --add-service=http --add-service=https
firewall-cmd --reload
```

### SELinux - Red Hat family only, and do not skip it

PHP-FPM runs in the `httpd_t` domain, which by default **may not open outbound
network connections**. Atlas is an outbound connection, so without this the
dashboard shows a connection timeout that looks exactly like a wrong
connection string:

```bash
setsebool -P httpd_can_network_connect 1
```

If the code lives outside `/var/www`, label it:

```bash
semanage fcontext -a -t httpd_sys_content_t "/srv/diviner-web(/.*)?"
restorecon -Rv /srv/diviner-web
```

To confirm SELinux is what is blocking something:

```bash
ausearch -m avc -ts recent
```

Debian and Ubuntu ship AppArmor, which does not restrict PHP-FPM's egress -
nothing to do there.

### TLS with certbot

Put a certificate on it before it faces the internet. The login cookie only
gets its `secure` flag once requests actually arrive over HTTPS, so until then
the session ID crosses the wire in clear.

Two things must be true before you ask for a certificate, or the challenge
fails and the rate limit is real (5 failures per hostname per hour):

- **DNS** for `pac.example.org` resolves to this box: `dig +short pac.example.org`
- **Port 80 is open** to the internet - Let's Encrypt validates over HTTP even
  when you only want HTTPS. Open 443 at the same time (step 5's firewall
  commands).
- **`server_name` is your real hostname.** `certbot --nginx` finds the block to
  edit by matching `-d` against `server_name`, so the vhost above still saying
  `pac.example.org` is the usual reason it reports it cannot find one. Confirm
  what nginx actually has - includes and all - with
  `nginx -T | grep server_name`.

#### Debian / Ubuntu

```bash
apt install -y certbot python3-certbot-nginx      # or python3-certbot-apache
certbot --nginx -d pac.example.org
```

The package installs a systemd timer (`certbot.timer`) that renews twice a
day. Nothing else to schedule.

#### Fedora, Rocky / Alma with EPEL enabled

```bash
dnf install -y certbot python3-certbot-nginx      # or python3-certbot-apache
certbot --nginx -d pac.example.org
systemctl enable --now certbot-renew.timer
```

#### RHEL and Amazon Linux - the venv install

Same reason as the Mongo driver: `certbot` is not in the base repositories. The
project's own recommendation is a virtualenv, which is distribution-proof and
updates independently of the system Python:

```bash
dnf install -y python3 python3-pip augeas-libs
python3 -m venv /opt/certbot/
/opt/certbot/bin/pip install --upgrade pip
/opt/certbot/bin/pip install certbot certbot-nginx    # certbot-apache for httpd
ln -sf /opt/certbot/bin/certbot /usr/bin/certbot

certbot --nginx -d pac.example.org
```

This install has **no timer of its own** - add one, or the certificate expires
in 90 days:

```bash
echo "0 0,12 * * * root /opt/certbot/bin/python -c 'import random, time; time.sleep(random.random() * 3600)' && /opt/certbot/bin/certbot renew -q" \
  >> /etc/crontab
```

#### Confirm renewal actually works

Do this once, now, rather than finding out in three months:

```bash
certbot renew --dry-run
certbot certificates          # names, expiry dates, the config file per cert
```

#### After the certificate is issued

`certbot --nginx` rewrites the vhost in place: it adds the `listen 443 ssl`
server, the certificate paths, and a redirect from port 80 if you accepted one.
Re-check the file afterwards - the `location ~ \.php$` block must still be
present in the **443** server, not only in the redirect stub:

```bash
nginx -t && systemctl reload nginx
curl -sI https://pac.example.org/ | head -3
```

On the Red Hat family, SELinux already allows nginx to read
`/etc/letsencrypt` - no boolean needed. If certbot cannot write its webroot
challenge, it is the firewall or DNS, not SELinux.

#### Behind a proxy or load balancer that terminates TLS

Do not run certbot on this box at all - certificate lives upstream. Instead
tell PHP the request was secure, or the login cookie stays non-secure and, on
some proxies, the login loops:

```nginx
fastcgi_param HTTPS on;
```

in the `location ~ \.php$` block of this *inner* server.

### Atlas network access

Add the server's **egress** IP to the Atlas allowlist - not the DNS record's
address, if those differ:

```bash
curl -4 https://ifconfig.me ; echo
```

Scope the database user to the `ghostd` database alone. Same advice as
`tools/pacdb/README.md` in the DIVINER repo.

---

## 6. Smoke test

```bash
curl -sI http://pac.example.org/           # 200, or 302 to ?page=login
curl -s  http://pac.example.org/ | head -40
```

Then in a browser: you should get the login page, not "No password is set" and
not a PHP error. Log in, and the dashboard should show the unit's document
counts and a database `ping` that answers.

Confirm FPM - not just the CLI - has the driver:

```bash
printf '<?php var_dump(extension_loaded("mongodb"));' > /var/www/DIVINER_Web/public/_x.php
curl -s http://pac.example.org/_x.php
rm -f /var/www/DIVINER_Web/public/_x.php     # delete it immediately
```

---

## Troubleshooting

| Symptom | Cause |
|---|---|
| **Page renders with no styling; `style.css` 302s or 403s** | The docroot is not readable by the *nginx* user - `chmod -R o=` does this on RHEL, where FPM runs as apache. See step 2. `tail /var/log/nginx/error.log` shows `stat() ... Permission denied`. |
| **"No password is set" though the pool clearly has the hash** | FPM ate the `$` signs. Move `password_hash` to `src/config.local.php`. See step 3. |
| **Correct password rejected every time** | A trailing space (or newline) on the stored hash - it must be exactly 60 characters. Check with `password_get_info()`; `algoName: unknown` means it is malformed. |
| **certbot: "Could not automatically find a matching server block"** | No `server_name` matches the `-d` hostname. Usually the example `pac.example.org` was never changed; also check the vhost is included (`sites-enabled` symlink on Debian, `conf.d/` on RHEL) and that nginx was reloaded after the edit. |
| **Steam: "Steam did not confirm that sign-in"** | The return URL did not match what was sent. Set `base_url` to the public address - usual behind a TLS-terminating proxy. Also check outbound HTTPS to `steamcommunity.com`. |
| **Steam: "may not use this site"** | Steam verified the id; it is not in `<unit>.admins`. Add it in game (STRUCTURE > ADMINS > ADD ME) or in `steam_admins`. |
| **`No match for argument: epel-release`** | Not Rocky or Alma. RHEL installs EPEL from a URL, Amazon Linux has no EPEL. See step 1. |
| **`No match for argument: php-pecl-mongodb`** | Expected on RHEL, Rocky, Alma and Amazon Linux - only Fedora and Remi package it. Add Remi or build it. |
| **"No password is set, so the site is closed."** | `password_hash` empty. The environment beats the file - an empty `GHOSTD_WEB_PASSWORD_HASH` set in the pool does *not* mask a good file value, but a stray one does confuse. Check both. |
| **"No connection string."** | `GHOSTD_MONGO` / `mongo_uri` not reaching PHP. With form B, confirm you edited the *pool* file and restarted FPM, not just reloaded nginx. |
| **HTTP 500, blank page** | Almost always the missing extension or a syntax error. Read the FPM log: `journalctl -u php8.2-fpm -n 50` / `journalctl -u php-fpm -n 50`, and the vhost error log. |
| **Dashboard: connection timed out** | On RHEL: `httpd_can_network_connect` is off. Otherwise: the server's IP is not in the Atlas allowlist, or egress on 27017 is blocked. |
| **Dashboard: authentication failed / SRV lookup failed** | Wrong user or password in the string; or a resolver that cannot answer `_mongodb._tcp` SRV queries. Test with `php -r 'new MongoDB\Driver\Manager(getenv("GHOSTD_MONGO"));'`. |
| **Login page loops - correct password, back to login** | The session cookie is not sticking. Check the session directory is writable by the FPM pool user: `/var/lib/php/sessions` (Debian, `www-data`) or `/var/lib/php/session` (RHEL, `apache`). If you changed the pool user, chown it to match. Behind a TLS proxy, see the `HTTPS on` note above. |
| **403 Forbidden on every page** | Document root points at the repository root instead of `public/`, or the code is not readable by the web user, or SELinux labels are wrong (`restorecon -Rv`). |
| **PHP source downloaded as text** | The `\.php$` location never matched - `fastcgi_pass` block missing or misplaced. Fix before anyone visits: this leaks nothing by itself (secrets live in `src/`), but it means PHP is not running at all. |
| **"Bad CSRF token"** | A stale tab after a session restart. Reload and retry. |

---

## Updating

```bash
cd /var/www/DIVINER_Web
git pull
systemctl reload php8.2-fpm     # php-fpm on RHEL; only needed if opcache is on
```

`src/config.local.php` is gitignored, so it survives a pull untouched. There is
no build step and no migration - the documents belong to the mod.

## Notes on hardening

- The site is a **single shared password** with no rate limiting. Put it behind
  a VPN, an IP allowlist, or basic auth if it is reachable from the open
  internet.
- `expose_php = Off` and `display_errors = Off` in the FPM php.ini for a public
  box. Errors are already caught and shown as a message by
  [public/index.php](public/index.php), so nothing useful is lost.
- Every write copies the previous document into `pac_backups` first. Do not set
  `backup_collection` to `''`.
- Edits made mid-mission are lost: the game server rewrites the store whole on
  SAVE and at mission end. Edit between sessions.

## The nightly backup

Every document in Mongo is written to one JSON file on the web server, once a
day, so an admin can still hand a mission its configuration when the service is
down. Nothing on the site writes to it and there is no restore button.

```
/var/www/DIVINER_Web/backups/latest.json          the whole database, one object
/var/www/DIVINER_Web/backups/latest.index.json    just the ids, for the page
/var/www/DIVINER_Web/backups/GHOST-<date>.json    kept 14 days
```

`root:apache 640`, outside `public/`, so nothing serves them and php-fpm can
read them. **There is no cron on this box** - it is a systemd timer:

```
/usr/local/sbin/diviner-backup.sh          = tools/backup.sh
/etc/systemd/system/diviner-backup.service
/etc/systemd/system/diviner-backup.timer   OnCalendar=*-*-* 04:15:00, Persistent
systemctl list-timers diviner-backup.timer
journalctl -u diviner-backup.service
```

The script pulls `GHOSTD_MONGO` and `GHOSTD_UNIT` out of `/etc/php-fpm.d/*.conf`
rather than keeping a second copy of them. Run it by hand with
`sudo systemctl start diviner-backup.service`.

The **Backup** page (admins only) shows when it was taken, how many documents it
holds, one document at a time in a read-only box, the whole file in a browser
tab, and a download link.

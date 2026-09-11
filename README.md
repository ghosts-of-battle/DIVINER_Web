# DIVINER_Web - a web manager for TAC//PAC

A small PHP site that reads and writes the same MongoDB documents the game
server does. It is for the time between sessions: fixing a rank, correcting a
name, reading the report deck, editing a config document without a mod rebuild.

```
nginx / Apache  -->  public/index.php  --ext-mongodb-->  MongoDB Atlas (ghostd.pac)
```

**No Composer, no build step, no daemon.** It uses the PECL `mongodb`
extension directly, so deploying is: install the extension, point a vhost at
`public/`, set two values.

## Requirements

- PHP **8.0 or newer** (uses `str_starts_with`)
- The **`mongodb`** PHP extension (`pecl install mongodb`, or
  `apt install php-mongodb`, or `yum install php-pecl-mongodb`)
- nginx or Apache
- A MongoDB database - the same one the mod uses. Atlas, or one you run
  yourself ([MONGODB.md](MONGODB.md))

## Install

Full Linux runbook - Debian/Ubuntu and RHEL/Rocky/Alma/Fedora, with SELinux, TLS
and troubleshooting - is in [DEPLOY.md](DEPLOY.md). The short version:

1. Copy this folder onto the web server.
2. Configure it, either way round - **the environment wins over the file**:

   **Environment** (nothing on disk to leak):
   ```
   GHOSTD_MONGO="mongodb+srv://user:password@cluster.../"
   GHOSTD_WEB_PASSWORD_HASH='$2y$...'
   GHOSTD_UNIT="framework"
   ```

   **Or a file**: copy `src/config.local.example.php` to
   `src/config.local.php` and fill it in. That file is gitignored.

3. Make the password hash:
   ```
   php -r "echo password_hash('your password', PASSWORD_DEFAULT), PHP_EOL;"
   ```
   With neither a password hash nor Steam sign-in configured, the site
   refuses every request rather than opening. See **Signing in** below.

4. **Atlas Network Access**: add the web server's public IP. The database user
   should be scoped to the `ghostd` database alone - the same advice as
   `tools/pacdb/README.md` in the DIVINER repo.

### nginx

```nginx
server {
    listen 80;
    server_name pac.example.org;
    root /var/www/DIVINER_Web/public;   # public/ ONLY - src/ must not be served
    index index.php;

    location / { try_files $uri $uri/ /index.php$is_args$args; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param GHOSTD_MONGO "mongodb+srv://...";
        fastcgi_param GHOSTD_WEB_PASSWORD_HASH "$2y$...";
        fastcgi_param GHOSTD_UNIT "framework";
    }
}
```

### Apache

```apache
<VirtualHost *:80>
    ServerName pac.example.org
    DocumentRoot /var/www/DIVINER_Web/public

    SetEnv GHOSTD_MONGO "mongodb+srv://..."
    SetEnv GHOSTD_WEB_PASSWORD_HASH "$2y$..."
    SetEnv GHOSTD_UNIT "framework"

    <Directory /var/www/DIVINER_Web/public>
        AllowOverride None
        Require all granted
    </Directory>
</VirtualHost>
```

**Put a TLS certificate on it before it faces the internet.** The login cookie
is marked `secure` automatically once the request arrives over HTTPS.

Only `public/` is ever served. `src/` sits above the document root on purpose -
if you must place everything in one servable folder, deny `src/` explicitly.

## Signing in

Two gates, and either is a complete configuration:

- **A shared password** - `password_hash`, the original. Simple, and it works
  when the database does not.
- **Sign in through Steam** - `steam_login`, and the site asks Steam who you
  are, then checks that id against **the mod's own admin list**.

The second is worth setting up because there is no new list to keep. PAC's
`<unit>.admins` document is keyed by Steam id - the same digits
`getPlayerUID` returns in game - so whoever can open the admin console in the
mission can sign in here, and `STRUCTURE > ADMINS > ADD ME` in game is also how
someone is given the site.

```php
'steam_login'          => true,
'steam_admins'         => ['76561198000000000'],  // extra ids, beyond the admin list
'steam_use_pac_admins' => true,                   // false to use steam_admins alone
'steam_api_key'        => '',                     // optional, persona names only
'base_url'             => '',                     // only behind a TLS proxy
```

**Steam proves who, not whether.** A valid Steam sign-in for an id on neither
list is refused, and the refusal shows the id so it can be added.

Keep the password set as well as Steam while you are finding your feet: if the
database is unreachable the admin list cannot be read, and Steam sign-in
refuses everyone until it comes back.

It is OpenID 2.0 - Steam has never offered anything else - written out in
`src/steam.php` rather than pulled from a library, in keeping with the rest of
the site. The assertion is verified by posting it back to Steam
(`check_authentication`); nothing in the redirect is trusted on its own. The
server needs outbound HTTPS to `steamcommunity.com`, but the site itself does
not need to be reachable from the internet - the redirect happens in the
browser, so a LAN box works.

## The pages

| Page | What |
|---|---|
| **Dashboard** | counts, the unit's ids, who is due a promotion, and every document grouped the way the mod files them |
| **Roster** | the store's players, sorted by name |
| **Player** | one record: rank, status, squad and role; skills, training, awards and notes, written the way the game's admin page writes them |
| **Applications** | who applied, and the questions they answered |
| **PAC actions** | the requests players raised |
| **Orders** | the operation orders, section by section |
| **Configs** | the unit's own records - ranks, skills, awards, statuses, promotion, training, admins |
| **Templates** | what a mission's `config\` folder held: the welcome screen, arsenals, motorpool, cosmetics, the vehicle spawner, logistics crates, pylon presets, custom traits, messaging nets - plus the **Report deck** and **System** (the operation order's shape, the request kinds and the colour schemes) |
| **ORBAT** | the orders of battle, communications, roles, squads and platoons |
| **Mongo docs** | every document, filterable, each openable as JSON |
| **Backup** | the nightly copy of the database, read only, admins only |
| **Branding** | the site's own name, colours and pictures - the one thing that is web only |
| **Web settings** | the public home page shown before sign-in: the logo, the name and an About block written in an HTML editor, four layouts to pick from, width and height sliders (Wysi, MIT, vendored in `public/` - the welcome screen's editor is the same one) |

Every page here has its opposite number in the game's TAC//PAC, and both write
the same documents, with four exceptions still to be built in game: the
logistics crates, the pylon presets, the report deck and the operation orders.

## What it will not do

- **It does not validate against the structure everywhere.** Typing `sergent`
  into a free-text rank field will be stored. Where a list exists - a squad, a
  role, a skill, an award, a course - the page offers it and checks it.
- **It does not restore a backup.** The backup page hands you the document; it
  never writes to Mongo.
- **It is not a live console.** The game server rewrites the store whole when
  an admin presses SAVE and at mission end, so an edit made mid-mission is
  lost. Edit between sessions.

## Safety

Every write copies the document's previous version into the `pac_backups`
collection first, with a timestamp and the source id. That is deliberate: the
PAC store has been lost twice to a write that looked harmless, so a mistake made
here is recoverable by copying a backup document back.

The store document's JSON editor additionally requires a confirmation tick, and
says why.

To restore a document by hand:

```js
// mongosh
const b = db.pac_backups.find({sourceId: "framework"}).sort({backedUpAt: -1}).limit(1).next()
db.pac.replaceOne({_id: "framework"}, Object.assign({_id: "framework"}, b.document))
```

## The documents

Set by the mod, not by this site - see `tools/pacdb/README.md` in DIVINER:

| Key | What |
|---|---|
| `<unit>` | the store: `players`, `sessions`, `windows`, `opords`, `log` |
| `<unit>.settings` | the settings section |
| `<unit>.ranks`, `.skills`, `.awards`, `.statuses`, `.nets`, `.radio`, `.templates`, `.schemes`, `.promotion`, `.trainings` | `{section, items}` |
| `<unit>.orbat` | `{faction, groups, platoons, radioNets}` |
| `<unit>.admins` | a plain `ids` list |
| `<unit>.role.<class>` | one role |
| `<unit>.opord.<id>` | one order |

A document edited here is read at the next mission start.

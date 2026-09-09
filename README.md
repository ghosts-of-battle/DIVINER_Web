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
- A MongoDB Atlas database - the same one the mod uses

## Install

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
   With no hash set the site refuses every request rather than opening.

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

## The pages

| Page | What |
|---|---|
| **Dashboard** | counts, the unit's ids, and every document grouped the way the mod files them |
| **Roster** | the store's players, sorted by name. Edits go one field at a time |
| **Report deck** | the templates document drawn as cards - lines, field keys, options |
| **Documents** | every document, filterable, each openable as JSON |

## What it will not do

- **It does not validate against the structure.** Typing `sergent` into a rank
  will be stored. The game's admin page checks ids against the structure; this
  does not. Use it for corrections you are sure of.
- **It does not edit skills, awards, notes, training or loadouts.** Those have
  shapes the game owns and validates.
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

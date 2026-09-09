# Self-hosting MongoDB instead of Atlas

[DEPLOY.md](DEPLOY.md) assumes MongoDB Atlas. This file replaces that with a
`mongod` you run yourself, on Debian/Ubuntu or the Red Hat family. Both
consumers speak plain `mongodb://`, so nothing in the mod or the site changes
except the connection string:

```
Arma server --> ghostd_pacdb extension --\
                                          >-- mongod (database ghostd, collection pac)
web app     --> PECL mongodb driver -----/
```

## Read this before you bind to anything

The game server's connection string is set as a **CBA server setting**, which
Arma sends **to every connected client** - `tools/pacdb/README.md` in the
DIVINER repo says so plainly. With Atlas that is harmless because the Network
Access allowlist holds only the game server's IP, so the string is useless from
a player's machine.

**Self-hosting has to reproduce that allowlist, or the string every player now
holds is a working login to your database.** That means a firewall rule
restricted to the game server's address - not "it has a password". Section 4
is not optional.

## Which box

| Layout | `bindIp` | Exposure |
|---|---|---|
| **Arma server, mongod and the site all on one machine** | `127.0.0.1` | none - simplest and safest |
| **mongod on the web box, Arma server elsewhere** | `127.0.0.1,<this box's IP>` | 27017 open **to the game server's IP only** |
| **mongod behind the pacdb service** (`tools/pacdb/service/`) | `127.0.0.1` | only 8085 is reachable; the game server never holds the Mongo string |

The middle row is the common one. The third is worth it if you would rather the
broadcast setting be an HTTP URL and an API key than database credentials.

---

## 1. Install MongoDB Community 8.0

**Check for AVX first.** MongoDB 5.0 and newer require it, and a cheap VPS
without it installs fine and then fails to start with an illegal instruction:

```bash
grep -o avx /proc/cpuinfo | head -1     # must print: avx
```

### Debian / Ubuntu

The distribution's own `mongodb` package is ancient or absent - use MongoDB's
repository:

```bash
apt install -y gnupg curl
curl -fsSL https://www.mongodb.org/static/pgp/server-8.0.asc \
  | gpg --dearmor -o /usr/share/keyrings/mongodb-server-8.0.gpg

# Debian 12 (bookworm):
echo "deb [ signed-by=/usr/share/keyrings/mongodb-server-8.0.gpg ] https://repo.mongodb.org/apt/debian bookworm/mongodb-org/8.0 main" \
  > /etc/apt/sources.list.d/mongodb-org-8.0.list

# Ubuntu 22.04 (jammy) - or noble for 24.04:
echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-8.0.gpg ] https://repo.mongodb.org/apt/ubuntu jammy/mongodb-org/8.0 multiverse" \
  > /etc/apt/sources.list.d/mongodb-org-8.0.list

apt update && apt install -y mongodb-org
systemctl enable --now mongod
```

The codename must match the release (`lsb_release -cs`) - a bookworm line on
Ubuntu gives a 404 at `apt update`.

### RHEL / Rocky / AlmaLinux 9

`/etc/yum.repos.d/mongodb-org-8.0.repo`:

```ini
[mongodb-org-8.0]
name=MongoDB Repository
baseurl=https://repo.mongodb.org/yum/redhat/9/mongodb-org/8.0/x86_64/
gpgcheck=1
enabled=1
gpgkey=https://pgp.mongodb.com/server-8.0.asc
```

```bash
dnf install -y mongodb-org
systemctl enable --now mongod
```

Use `.../redhat/8/...` on RHEL 8. **Amazon Linux 2023** uses its own path:
`baseurl=https://repo.mongodb.org/yum/amazon/2023/mongodb-org/8.0/x86_64/`.

No EPEL and no Remi involved - this is MongoDB's own repository, so the two
"No match for argument" errors from the PHP driver do not repeat here.

### Verify

```bash
systemctl status mongod --no-pager
mongosh --eval 'db.runCommand({ping:1})'
```

`mongosh` and `mongodump` come with the `mongodb-org` metapackage.

---

## 2. Users, then authorization

Create the users **before** turning auth on - once it is on, you need a user to
create users, and the localhost exception only helps the first one.

```bash
mongosh
```

```js
use admin
db.createUser({
  user: "root",
  pwd: passwordPrompt(),
  roles: [ { role: "userAdminAnyDatabase", db: "admin" }, "readWriteAnyDatabase" ]
})

// The site and the game server. Separate users so either can be rotated
// alone - the game server's password is the one every player holds.
use ghostd
db.createUser({ user: "ghostd_web",  pwd: passwordPrompt(), roles: [ { role: "readWrite", db: "ghostd" } ] })
db.createUser({ user: "ghostd_game", pwd: passwordPrompt(), roles: [ { role: "readWrite", db: "ghostd" } ] })
exit
```

Neither application user can touch any other database, which is the same
scoping `tools/pacdb/README.md` asks for on Atlas.

Now turn authorization on in `/etc/mongod.conf`:

```yaml
security:
  authorization: enabled
```

```bash
systemctl restart mongod
mongosh --eval 'db.runCommand({ping:1})'          # still works, ping is free
mongosh "mongodb://ghostd_web:PASSWORD@127.0.0.1:27017/?authSource=ghostd" \
        --eval 'db.getSiblingDB("ghostd").pac.countDocuments({})'
```

`authSource=ghostd` matters: the user was created **in** `ghostd`, so
authenticating against `admin` fails with "Authentication failed" even though
the password is right.

---

## 3. The connection strings

### The web app

In `src/config.local.php`, or `GHOSTD_MONGO` in the FPM pool
([DEPLOY.md](DEPLOY.md) step 3):

```
mongodb://ghostd_web:PASSWORD@127.0.0.1:27017/?authSource=ghostd
```

`database` stays `ghostd` and `collection` stays `pac` - unchanged from Atlas.

**Red Hat family:** the site still cannot reach a *local* mongod until SELinux
allows PHP-FPM to open sockets at all:

```bash
setsebool -P httpd_can_network_connect 1
```

That boolean covers 127.0.0.1 too - localhost is not an exemption.

### The game server

Per `tools/pacdb/README.md`, in order of precedence: the CBA server setting
**Database**, then `GHOSTD_PACDB_URL` in the server machine's environment, then
`pacdb.json` in the server root.

```
mongodb://ghostd_game:PASSWORD@db.example.org:27017/?authSource=ghostd
```

Prefer `pacdb.json` or the environment variable over the CBA setting where you
can - those two stay on the server machine, and only the CBA setting is
broadcast to clients.

---

## 4. Exposing it to the game server, safely

Skip this whole section if Arma runs on the same box - leave `bindIp` at
`127.0.0.1` and you are done.

`/etc/mongod.conf`:

```yaml
net:
  port: 27017
  bindIp: 127.0.0.1,203.0.113.10      # this box's own address, never 0.0.0.0
```

```bash
systemctl restart mongod
```

Then open the port **to one address**, which is the whole point:

```bash
# Debian / Ubuntu
ufw allow from 198.51.100.20 to any port 27017 proto tcp

# Red Hat family
firewall-cmd --permanent --add-rich-rule='rule family="ipv4" source address="198.51.100.20/32" port port="27017" protocol="tcp" accept'
firewall-cmd --reload
```

`198.51.100.20` is the **game server's** public IP. Confirm from the outside
that nobody else can get in:

```bash
# from any other machine - must time out or refuse
nc -zv db.example.org 27017
```

If you moved mongod off 27017, SELinux needs to be told:

```bash
semanage port -a -t mongod_port_t -p tcp 27018
```

### TLS, if it crosses the internet

Credentials go over the wire on every connection, and the game server's are in
every player's memory. Reuse the certbot certificate from
[DEPLOY.md](DEPLOY.md) - mongod wants one concatenated PEM:

```bash
install -d -m 0700 -o mongod -g mongod /etc/mongod-tls
cat /etc/letsencrypt/live/pac.example.org/fullchain.pem \
    /etc/letsencrypt/live/pac.example.org/privkey.pem \
    > /etc/mongod-tls/mongod.pem
chown mongod:mongod /etc/mongod-tls/mongod.pem && chmod 600 /etc/mongod-tls/mongod.pem
```

```yaml
net:
  tls:
    mode: requireTLS
    certificateKeyFile: /etc/mongod-tls/mongod.pem
```

The certificate is renewed every 60 days and mongod will not notice, so rebuild
the PEM on renewal - `/etc/letsencrypt/renewal-hooks/deploy/mongod.sh`, `chmod
+x`:

```bash
#!/bin/sh
cat /etc/letsencrypt/live/pac.example.org/fullchain.pem \
    /etc/letsencrypt/live/pac.example.org/privkey.pem \
    > /etc/mongod-tls/mongod.pem
chown mongod:mongod /etc/mongod-tls/mongod.pem
chmod 600 /etc/mongod-tls/mongod.pem
systemctl restart mongod
```

Clients then need `&tls=true` on the connection string. Test it before you rely
on it: `certbot renew --dry-run` does not run deploy hooks, so run the script
by hand once.

---

## 5. Moving the data off Atlas

```bash
mongodump  --uri="mongodb+srv://user:pass@cluster.mongodb.net/" --db ghostd -o /tmp/atlas
mongorestore --uri="mongodb://root:pass@127.0.0.1:27017/?authSource=admin" \
             --db ghostd /tmp/atlas/ghostd
rm -rf /tmp/atlas          # it holds the whole roster in clear
```

Check the count matches what the Atlas dashboard showed, then open the site's
Documents page - the ids should be the unit's `framework`, `framework.ranks`
and so on.

Starting empty instead? Seed it from a mission's `config\` with
`push_config.py <mission>\config` from `tools/pacdb/` on an admin's PC.

---

## 6. Back it up - this store has been lost twice

The site copies every document it replaces into `pac_backups`, but that does
not survive the disk. Atlas had snapshots; a box you run does not, until you
make them.

`/etc/cron.daily/ghostd-backup`, `chmod +x`:

```bash
#!/bin/sh
set -e
d=/var/backups/ghostd
mkdir -p "$d"
mongodump --uri="mongodb://root:PASSWORD@127.0.0.1:27017/?authSource=admin" \
          --db ghostd --gzip --archive="$d/ghostd-$(date +%F).gz"
find "$d" -name 'ghostd-*.gz' -mtime +30 -delete
```

`chmod 600` it - it contains the root password - and verify a restore once,
into a throwaway database:

```bash
mongorestore --uri="mongodb://root:PASSWORD@127.0.0.1:27017/?authSource=admin" \
             --gzip --archive=/var/backups/ghostd/ghostd-2026-09-09.gz \
             --nsFrom 'ghostd.*' --nsTo 'ghostd_restoretest.*'
```

`pac_backups` grows one document per edit and is never pruned. Trim it
occasionally:

```js
db.pac_backups.deleteMany({ backedUpAt: { $lt: "2026-06-01 00:00:00" } })
```

---

## Troubleshooting

| Symptom | Cause |
|---|---|
| `mongod` dies at once, `Illegal instruction` | No AVX on this CPU. MongoDB 5.0+ requires it - use 4.4, or a different machine. |
| `apt update`: 404 on the MongoDB repo | Codename does not match the release. `lsb_release -cs`, and note Debian uses `bookworm/mongodb-org/8.0 main`, Ubuntu `jammy/... multiverse`. |
| `Authentication failed` with the right password | Missing `?authSource=ghostd`. The user lives in `ghostd`, not `admin`. |
| Site: `connection refused` to 127.0.0.1:27017 | `mongod` not running, or `bindIp` excludes localhost. |
| Site: `connection timed out`, mongosh fine on the same box | SELinux - `setsebool -P httpd_can_network_connect 1`. |
| Game server `.rpt`: `configure` fine, `get` times out | Firewall or `bindIp`. The rich rule must name the game server's *current* public IP. |
| `mongod` will not start after editing the config | YAML: tabs are illegal and indentation is meaningful. `mongod --config /etc/mongod.conf --outputConfig` parses without starting. |
| Permission denied on `/var/lib/mongo` after a restore | Files written as root. `chown -R mongod:mongod /var/lib/mongo` (`/var/lib/mongodb` on Debian). |

## What did not change

The documents, their shapes, and the fact that a document edited on the site is
read at the **next mission start** - the game server rewrites the store whole
on SAVE and at mission end, so a mid-mission edit is lost either way. Hosting
the database yourself changes none of that.

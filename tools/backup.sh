#!/bin/sh
# The nightly database dump, as the server runs it.
#
# Installed at /usr/local/sbin/diviner-backup.sh and fired by the systemd timer
# diviner-backup.timer (04:15 UTC daily). There is no cron on this box.
#
# GHOSTD_MONGO and GHOSTD_UNIT are env[] lines in the php-fpm pool, not in
# src/config.local.php, so they are pulled from there rather than written down
# in a second place that can drift.
set -e

eval $(grep -h "^env\[GHOSTD_" /etc/php-fpm.d/*.conf \
    | sed -e "s/env\[\([A-Z_]*\)\][ ]*=[ ]*/\1=/" -e "s/^/export /")

cd /var/www/DIVINER_Web
/usr/bin/php tools/backup.php --keep 14

# php-fpm reads as apache; the backup is not servable and not world readable.
chown root:apache backups/*.json
chmod 640 backups/*.json

#!/bin/bash
usermod -d /var/lib/mysql/ mysql

# --- Secret injection ------------------------------------------------------
# DB_PASSWORD is injected from the K8s 'eds-db' Secret (see eds.yaml env). The
# password is NEVER committed or baked into the image; we materialise it here,
# at container start, into the places the various consumers read:
#   * MySQL: CREATE USER below uses it directly.
#   * MySQL client tools (backup.sh, sensorcfg.php mysqldump, tests): a root
#     ~/.my.cnf so no `-p<pw>` appears on any command line.
#   * PHP dashboards: config.txt is rendered from config.txt.tmpl.
#   * C++ collector: edsServerHandlerConf.txt is rendered from its .tmpl.
# All rendered files live only inside the running container (ephemeral), never
# in git or the image layers.
if [[ -z "${DB_PASSWORD:-}" ]]; then
  echo "FATAL: DB_PASSWORD env is not set (expected from Secret 'eds-db'). Refusing to start." >&2
  exit 1
fi

# Render the PHP config and the collector conf from committed templates by
# substituting the secret placeholder. Templates carry __DB_PASSWORD__.
# getConfig() reads config.txt relative to the PHP script dir (__DIR__), and the
# report cron runs from /homedata/visualize, so render there. Also render into
# /var/www/html if a copy is served from the docroot.
for d in /homedata/visualize /var/www/html; do
  if [[ -f "$d/config.txt.tmpl" ]]; then
    sed "s|__DB_PASSWORD__|${DB_PASSWORD}|g" "$d/config.txt.tmpl" > "$d/config.txt"
    chown www-data:www-data "$d/config.txt" 2>/dev/null || true
  fi
done
if [[ -f /homedata/edssensors/edsServerHandlerConf.txt.tmpl ]]; then
  sed -e "s|__DB_PASSWORD__|${DB_PASSWORD}|g" \
      -e "s|__SMTP_PASSWORD__|${SMTP_PASSWORD:-}|g" \
      /homedata/edssensors/edsServerHandlerConf.txt.tmpl > /homedata/edssensors/edsServerHandlerConf.txt
fi
# ---------------------------------------------------------------------------

service mysql start
until mysql -u root -e "SELECT 1" &>/dev/null; do sleep 1; done
service mysql status
service ssh start
service ssh status
service apache2 start

# --- Normalize /usr/storage ownership (runs as root, before restore/seed) ---
# The PVC is shared between actors that run as different users:
#   * apache2/PHP (www-data) reads & writes ips/ and the sensorconfig.sql seed
#   * root cron / preStop run backup.sh, which writes the test*.tar dumps
# Files can arrive on the volume with inconsistent ownership (fresh PVC, files
# copied in from another machine, or created by a different user). Make it
# deterministic on every start so app writes never fail on a wrong owner.
# Each chown is guarded so a fresh/empty PVC does not error.
if [[ -d /usr/storage ]]; then
  chown www-data:www-data /usr/storage 2>/dev/null || true
  [[ -d /usr/storage/ips ]] && chown -R www-data:www-data /usr/storage/ips/ 2>/dev/null || true
  # App-writable seed (edited via mysqldump as root AND read by PHP): www-data.
  [[ -e /usr/storage/sensorconfig.sql ]] && chown www-data:www-data /usr/storage/sensorconfig.sql 2>/dev/null || true
  # Backup archives are only ever written by root cron and read by root restore.sh.
  # Keep them root-owned; normalize in case any were copied in as another user.
  for f in /usr/storage/test*.tar; do
    [[ -e "$f" ]] && chown root:root "$f" 2>/dev/null || true
  done
fi
# ---------------------------------------------------------------------------
# Create/refresh the app DB user from the injected secret. Run as root (the
# unix_socket root account) — ~/.my.cnf is written AFTER this, so bare `mysql`
# here must not yet assume dbuser. DROP first so a rotated password takes effect
# on restart. localhost-only user; mydb is local to this container.
mysql -u root <<SQL
DROP USER IF EXISTS 'dbuser'@'localhost';
CREATE USER 'dbuser'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON *.* TO 'dbuser'@'localhost';
FLUSH PRIVILEGES;
SQL

# Now that dbuser exists, write the root MySQL client config so subsequent bare
# `mysql`/`mysqldump` (restore.sh, createSensorConfig.sh, backup.sh cron,
# sensorcfg.php) authenticate as dbuser without -p on any command line. 0600.
cat > /root/.my.cnf <<EOF
[client]
user=dbuser
password=${DB_PASSWORD}
EOF
chmod 600 /root/.my.cnf

./restore.sh
if [[ $? -ne 0 ]]; then
  echo "WARNING: restore.sh failed, continuing with fresh DB"
fi
./createSensorConfig.sh
service cron start
./start_eds.sh
python3 /homedata/edssensors/eds_web.py &
cd ../scripts/
python3 hueTemps.py
cd -


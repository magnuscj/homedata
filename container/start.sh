#!/bin/bash
usermod -d /var/lib/mysql/ mysql
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
echo "CREATE USER 'dbuser'@'localhost' IDENTIFIED BY 'kmjmkm54C#';" | mysql
echo "GRANT ALL PRIVILEGES ON * . * TO 'dbuser'@'localhost';" | mysql
echo "FLUSH PRIVILEGES;" | mysql
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


#!/bin/bash
usermod -d /var/lib/mysql/ mysql
service mysql start
until mysql -u root -e "SELECT 1" &>/dev/null; do sleep 1; done
service mysql status
service ssh start
service ssh status
service apache2 start
chown -R www-data:www-data /usr/storage/ips/
chown www-data:www-data /usr/storage
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


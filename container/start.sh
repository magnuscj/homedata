#!/bin/bash
usermod -d /var/lib/mysql/ mysql
service mysql start
service mysql status
service ssh start
service ssh status
service apache2 start
echo "CREATE USER 'dbuser'@'localhost' IDENTIFIED BY 'kmjmkm54C#';" | mysql
echo "GRANT ALL PRIVILEGES ON * . * TO 'dbuser'@'localhost';" | mysql
echo "FLUSH PRIVILEGES;" | mysql
sleep 5s
./restore.sh
./createSensorConfig.sh
service cron start
./start_eds.sh
cd ../scripts/
python3 hueTemps.py
cd -
#./eds 192.168.1.87 192.168.1.84 192.168.1.172 192.168.1.128 192.168.1.230


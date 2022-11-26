#!/bin/bash
usermod -d /var/lib/mysql/ mysql
service mysql start
service mysql status
service apache2 start
echo "CREATE USER 'dbuser'@'localhost' IDENTIFIED BY 'kmjmkm54C#';" | mysql
echo "GRANT ALL PRIVILEGES ON * . * TO 'dbuser'@'localhost';" | mysql
echo "FLUSH PRIVILEGES;" | mysql
sleep 5s
./restore.sh
./createSensorConfig.sh
service cron start
./start_eds.sh
#./eds 192.168.1.87 192.168.1.84 192.168.1.172 192.168.1.128 192.168.1.230


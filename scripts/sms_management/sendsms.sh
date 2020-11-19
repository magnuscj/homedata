#/bin/bash!

MSG=`apcaccess status | grep STATUS | awk '{print $3}'  | tr -d '\n'`
echo $MSG
echo $MSG | gammu --sendsms TEXT 0730435623

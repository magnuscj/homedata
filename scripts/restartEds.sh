#!/bin/bash
echo "Started "$(date)
while [ 1 ]
do
  REACT=$(ping 192.168.1.245 -c 1 | grep Unreachable | awk '{print "false"}')

  if [[ $REACT == "false" ]];
  then
    echo "Restarted "$(date)
    curl --request PUT --data '{"on":false}' http://192.168.50.151/api/HTymPjBT0g1JdTwXFdYe-N26G9IQ8MDQ8quVIkr1/lights/18/state
    echo " "
    sleep 50
    curl --request PUT --data '{"on":true}' http://192.168.50.151/api/HTymPjBT0g1JdTwXFdYe-N26G9IQ8MDQ8quVIkr1/lights/18/state
    echo " "
    sleep 3600
  fi
  sleep 800
done
echo "Done!"

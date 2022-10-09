#!/bin/bash

#Retreive external IP
ip=$(dig +short myip.opendns.com @resolver1.opendns.com)

echo "Current ip  : $EXTERNAL_IP"
echo "Retreived ip: $ip"

if [[ $ip != $EXTERNAL_IP ]]; then
  if [[ $ip =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]]; then
    export EXTERNAL_IP=$ip
    echo "Current ip  : $EXTERNAL_IP"
    if [[ -z $1 ]]; then
      echo "No template provided"
    else
      #Create file with new ip
      sed "s/#IP#/$ip/g" "tmpl_$1" > $1
    fi
  else
    echo "fail"
  fi
fi

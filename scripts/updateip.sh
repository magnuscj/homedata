#!/bin/bash

#Retreive external IP
ip=$(dig +short myip.opendns.com @resolver1.opendns.com)

if [[ $ip =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] ; then
  if [[ $ip != $EXTERNAL_IP ]] ; then
    export EXTERNAL_IP=$ip
  fi
else
  echo "Fail - no ip retreived"  
fi

if [[ -z $1 ]]; then
      echo "No template provided"
else
  #Create file with new ip
  if [[ $EXTERNAL_IP =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] ; then
    mkdir -p ~/tmpviz || true
    sed "s/#IP#/$ip/g" "tmpl_$1" > ~/tmpviz/$1    
  else
    echo "The external IP $EXTERNAL_IP isn't a valid IP"
  fi
fi

#!/bin/bash
# Keeps port-forward alive across pod restarts

echo "Starting port-forward on 30164 -> 80"
while true; do
  kubectl port-forward service/eds-ext-nordenort-service 30164:80
  echo "Port-forward died, restarting in 2s..."
  sleep 2
done

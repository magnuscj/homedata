#!/bin/bash
service ssh start
service ssh status
service apache2 start
service cron start

# Supervise the collector: if it ever exits, log and respawn after 5s so a
# transient crash can never terminate the container (which would otherwise
# flap between Completed/Running under kubelet restarts).
while true; do
    echo "start.sh: launching huesensors.py"
    python3 huesensors.py
    echo "start.sh: huesensors.py exited (rc=$?); respawning in 5s"
    sleep 5
done

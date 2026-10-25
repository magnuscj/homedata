#!/bin/bash
service ssh start
service ssh status
service apache2 start
service cron start

# Supervise the collector: respawn if it ever exits so the container stays up.
while true; do
    echo "start.sh: launching elprice.py"
    python3 elprice.py
    echo "start.sh: elprice.py exited (rc=$?); respawning in 5s"
    sleep 5
done

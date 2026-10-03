#!/bin/bash
# supervise_eds.sh — keep the eds collector running.
#
# The collector (./eds) was previously launched ONCE in the background by
# start.sh with no supervision: if its initial launch didn't survive (e.g. it
# started before MySQL was ready, or exited for any reason) nothing restarted
# it, and the pod ran without collecting data until a manual restart. This loop
# owns the collector's lifecycle:
#   * wait until MySQL accepts connections before the first launch;
#   * run ./eds in the FOREGROUND of the loop so we notice when it exits;
#   * respawn it after a short backoff whenever it dies.
#
# start.sh runs this in the background. The manual/one-shot path (start_eds.sh,
# used by create_ips.php and manual restarts) just KILLS the running collector;
# this supervisor then respawns it within a few seconds — so there is always
# exactly one owner of the launch.
#
# Poll list comes from the PVC file /usr/storage/ips/ips.txt (NOT the baked-in
# repo ips.txt). See the infra-inventory skill.

cd /homedata/edssensors || exit 1

# Wait for the local MySQL to be ready so the collector's first DB connect
# succeeds (its own retry loop is short and does not recover well on a cold DB).
until mysql -e "SELECT 1" >/dev/null 2>&1; do
  echo "supervise_eds: waiting for mysqld... ($(date))" >> /tmp/eds_output.txt
  sleep 1
done

while true; do
  echo "supervise_eds: launching eds ($(date))" >> /tmp/eds_output.txt
  # Foreground within the loop; tee keeps the collector's output for debugging.
  ./eds $(< /usr/storage/ips/ips.txt) | tee -a /tmp/eds_output.txt
  echo "supervise_eds: eds exited rc=${PIPESTATUS[0]} ($(date)); respawning in 5s" >> /tmp/eds_output.txt
  sleep 5
done

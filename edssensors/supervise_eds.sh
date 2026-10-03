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
#
# The following are overridable for tests only; the defaults are the production
# values so in-pod behaviour is unchanged:
#   EDS_DIR              working dir containing the eds binary (def /homedata/edssensors)
#   IPS_FILE             poll-list file (def /usr/storage/ips/ips.txt)
#   EDS_LOG              output log (def /tmp/eds_output.txt)
#   SUPERVISE_BACKOFF    seconds to wait before respawn (def 5)
#   SUPERVISE_MAX_ITERS  stop after N relaunches; 0 = infinite (def 0)

EDS_DIR="${EDS_DIR:-/homedata/edssensors}"
IPS_FILE="${IPS_FILE:-/usr/storage/ips/ips.txt}"
EDS_LOG="${EDS_LOG:-/tmp/eds_output.txt}"
SUPERVISE_BACKOFF="${SUPERVISE_BACKOFF:-5}"
SUPERVISE_MAX_ITERS="${SUPERVISE_MAX_ITERS:-0}"

cd "$EDS_DIR" || exit 1

# Wait for the local MySQL to be ready so the collector's first DB connect
# succeeds (its own retry loop is short and does not recover well on a cold DB).
until mysql -e "SELECT 1" >/dev/null 2>&1; do
  echo "supervise_eds: waiting for mysqld... ($(date))" >> "$EDS_LOG"
  sleep 1
done

iters=0
while true; do
  echo "supervise_eds: launching eds ($(date))" >> "$EDS_LOG"
  # Foreground within the loop; tee keeps the collector's output for debugging.
  ./eds $(< "$IPS_FILE") | tee -a "$EDS_LOG"
  echo "supervise_eds: eds exited rc=${PIPESTATUS[0]} ($(date)); respawning in ${SUPERVISE_BACKOFF}s" >> "$EDS_LOG"
  iters=$((iters + 1))
  if [[ "$SUPERVISE_MAX_ITERS" -gt 0 && "$iters" -ge "$SUPERVISE_MAX_ITERS" ]]; then
    echo "supervise_eds: reached SUPERVISE_MAX_ITERS=$SUPERVISE_MAX_ITERS, exiting" >> "$EDS_LOG"
    break
  fi
  sleep "$SUPERVISE_BACKOFF"
done

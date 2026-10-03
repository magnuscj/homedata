#!/bin/bash
# start_eds.sh — request a restart of the eds collector.
#
# The collector's lifecycle is owned by supervise_eds.sh (started by start.sh),
# which respawns ./eds within a few seconds whenever it exits. So "restarting"
# the collector — e.g. after create_ips.php updates /usr/storage/ips/ips.txt, or
# a manual restart — just means killing the current ./eds; the supervisor then
# relaunches it with the new ips list. (Guard pgrep so kill does not error when
# no collector is running.)
#
# NOTE: this no longer launches ./eds itself. If supervise_eds.sh is somehow not
# running, fall back to a one-shot launch so a manual invocation still works.
#
# EDS_DIR / IPS_FILE / EDS_LOG are overridable for tests; defaults are the
# production values so in-pod behaviour is unchanged.

EDS_DIR="${EDS_DIR:-/homedata/edssensors}"
IPS_FILE="${IPS_FILE:-/usr/storage/ips/ips.txt}"
EDS_LOG="${EDS_LOG:-/tmp/eds_output.txt}"

PIDS=$(pgrep -x eds)
if [[ -n "$PIDS" ]]; then
  kill -9 $PIDS
fi

# Fallback: if no supervisor is running, do a one-shot launch so manual use
# (outside the pod's start.sh) still starts the collector.
if ! pgrep -f "supervise_eds.sh" >/dev/null 2>&1; then
  cd "$EDS_DIR"
  ./eds $(< "$IPS_FILE") | tee -a "$EDS_LOG" &
fi

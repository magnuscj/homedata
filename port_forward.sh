#!/bin/bash
# Keeps a kubectl port-forward alive across pod restarts.
#
# Hardened vs. the old bare `while true` loop:
#   - Kills any stale forwarder holding the port before starting, so this can be
#     re-launched (new terminal, systemd restart) without "address already in use".
#   - A curl watchdog respawns the forwarder when it goes HALF-DEAD (kubectl keeps
#     the :PORT listen socket open but the tunnel to :80 times out) — the failure
#     mode the bare loop never recovered from because kubectl never exits.

set -u

PORT=30164
SVC=service/eds-ext-nordenort-service
# kubectl is not always on a non-login shell PATH on this host (it lives in
# /usr/local/bin); resolve it so systemd/cron launches work too.
KUBECTL=$(command -v kubectl || echo /usr/local/bin/kubectl)

cleanup() {
  echo "Shutting down, killing forwarder..."
  [ -n "${PF_PID:-}" ] && kill "$PF_PID" 2>/dev/null
  exit 0
}
trap cleanup INT TERM

# Clean up any stale/zombie forwarder already holding the port.
pkill -f "port-forward $SVC" 2>/dev/null && { echo "Killed stale forwarder"; sleep 1; }

echo "Starting port-forward on $PORT -> 80 ($SVC)"
while true; do
  "$KUBECTL" port-forward "$SVC" "${PORT}:80" &
  PF_PID=$!

  # Watchdog: while kubectl is alive, periodically verify the tunnel actually serves.
  while kill -0 "$PF_PID" 2>/dev/null; do
    sleep 10
    if ! curl -fs -m 5 "http://127.0.0.1:${PORT}/" >/dev/null 2>&1; then
      echo "Port-forward unhealthy (half-dead?), killing $PF_PID"
      kill "$PF_PID" 2>/dev/null
      break
    fi
  done

  wait "$PF_PID" 2>/dev/null
  echo "Port-forward died, restarting in 2s..."
  sleep 2
done

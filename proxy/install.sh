#!/bin/bash
# Install the eds edge reverse proxy as a permanent, supervised service.
#
#   client -> TUF-AX5400 router NAT :8090 -> nginx (this host) -> NodePort 30164 -> eds pod
#
# What it sets up:
#   * /etc/eds-proxy/nginx-eds.conf   (the proxy + real-IP logging config)
#   * /etc/systemd/system/eds-proxy.service (supervised nginx container)
#   * /etc/logrotate.d/eds-proxy      (size-capped, rotated, compressed logs)
#   * /home/magnus/eds-logs           (host log dir)
#
# Router: forward external :8090 -> <this host LAN IP>:8090.
# Prereqs: docker installed; NodePort 30164 reachable from this host
#          (verify: curl -s -o /dev/null -w '%{http_code}\n' http://192.168.49.2:30164/).
#
# Run with sudo. Idempotent: safe to re-run after editing nginx-eds.conf.
set -euo pipefail

SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOGDIR="/home/magnus/eds-logs"

if [[ $EUID -ne 0 ]]; then
  echo "Please run as root (sudo $0)" >&2
  exit 1
fi

echo "1/5 Creating config + log directories..."
install -d -m 0755 /etc/eds-proxy
install -d -m 0755 "$LOGDIR"

echo "2/5 Installing nginx config..."
install -m 0644 "$SRC_DIR/nginx-eds.conf" /etc/eds-proxy/nginx-eds.conf

echo "3/5 Installing systemd unit..."
install -m 0644 "$SRC_DIR/eds-proxy.service" /etc/systemd/system/eds-proxy.service
systemctl daemon-reload

echo "4/5 Installing logrotate rule..."
install -m 0644 "$SRC_DIR/logrotate-eds" /etc/logrotate.d/eds-proxy
# Validate the rule parses (debug = no changes made).
logrotate --debug /etc/logrotate.d/eds-proxy >/dev/null && echo "    logrotate rule OK"

echo "5/5 Enabling + starting eds-proxy..."
systemctl enable eds-proxy
systemctl restart eds-proxy
sleep 2
systemctl --no-pager --full status eds-proxy | head -n 8 || true

cat <<EOF

Done. The edge proxy is running and will start on boot.

Next:
  * Point the TUF-AX5400 port-forward :8090 -> $(hostname -I | awk '{print $1}'):8090
  * The old port_forward.sh (kubectl port-forward) is now SUPERSEDED and can be
    stopped — nginx reaches the NodePort (192.168.49.2:30164) directly.

Verify real client IPs (internal/LAN addresses are filtered out):
  tail -f $LOGDIR/eds_access.log

Manage:
  systemctl status eds-proxy
  systemctl restart eds-proxy      # after editing /etc/eds-proxy/nginx-eds.conf
  journalctl -u eds-proxy -f
EOF

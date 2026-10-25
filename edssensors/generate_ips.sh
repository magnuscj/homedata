#!/bin/bash
# generate_ips.sh — build the eds collector poll list from LIVE cluster state.
#
# Poll list (/usr/storage/ips/ips.txt) =
#     (1) every "<name>-ext-nordenort-service" ClusterIP discovered via the
#         Kubernetes API (discover_services.sh --ips-only), THEN
#     (2) the static non-service devices listed in static-services.txt.
#
# WHY: the ClusterIPs differ per cluster (sandbox docker-desktop vs prod
# minikube) and change if a Service is recreated. Generating ips.txt at boot
# from discovery means the COMMITTED repo ips.txt no longer drives runtime — so
# a git pull can no longer clobber one cluster's list with another's (the
# 2026-10-25 incident). The only committed, shared input is static-services.txt
# (physical LAN devices, identical everywhere).
#
# SAFETY invariants:
#   * If discovery fails but a non-empty ips.txt already exists, KEEP it
#     (never replace a working poll list with nothing).
#   * Refuse to write an EMPTY list.
#   * De-duplicate while preserving order (discovered first, then static).
#
# Called by container/start.sh before supervise_eds.sh, and by create_ips.php
# after a UI save. The collector supervisor re-reads ips.txt on every (re)launch,
# so a regenerated file takes effect on the next collector respawn.
#
# Overridable for tests (defaults are the in-pod production values):
#   DISCOVER      path to discover_services.sh (def: alongside this script)
#   STATIC_FILE   static device list           (def: alongside this script)
#   IPS_FILE      output poll list             (def: /usr/storage/ips/ips.txt)
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DISCOVER="${DISCOVER:-${SCRIPT_DIR}/discover_services.sh}"
STATIC_FILE="${STATIC_FILE:-${SCRIPT_DIR}/static-services.txt}"
IPS_FILE="${IPS_FILE:-/usr/storage/ips/ips.txt}"

log() { echo "generate_ips: $*" >&2; }

tmp="$(mktemp)"
trap 'rm -f "$tmp"' EXIT

# (1) Discovered Service ClusterIPs (sorted by service name inside discover).
discovery_ok=0
if [[ -x "$DISCOVER" ]] || [[ -f "$DISCOVER" ]]; then
  if "$DISCOVER" --ips-only >> "$tmp" 2>/dev/null; then
    discovery_ok=1
  fi
fi
if [[ "$discovery_ok" -ne 1 ]]; then
  log "WARNING: service discovery failed or unavailable ($DISCOVER)"
  if [[ -s "$IPS_FILE" ]]; then
    log "keeping existing non-empty $IPS_FILE unchanged"
    exit 0
  fi
  log "no existing poll list to fall back on; will use static entries only"
fi

# (2) Static non-service devices (ignore comments / blank lines).
if [[ -f "$STATIC_FILE" ]]; then
  grep -vE '^[[:space:]]*(#|$)' "$STATIC_FILE" >> "$tmp" || true
else
  log "NOTE: no static-services.txt at $STATIC_FILE (continuing without static IPs)"
fi

# De-duplicate preserving order; drop any blank lines.
out="$(awk 'NF && !seen[$0]++' "$tmp")"

if [[ -z "$out" ]]; then
  log "ERROR: produced an EMPTY poll list; refusing to write."
  if [[ -s "$IPS_FILE" ]]; then
    log "keeping existing $IPS_FILE unchanged"
    exit 0
  fi
  exit 1
fi

mkdir -p "$(dirname "$IPS_FILE")"
printf '%s\n' "$out" > "$IPS_FILE"
chown www-data:www-data "$IPS_FILE" 2>/dev/null || true

log "wrote $(printf '%s\n' "$out" | grep -c .) IPs to $IPS_FILE"

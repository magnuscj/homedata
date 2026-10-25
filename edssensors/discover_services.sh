#!/bin/bash
# discover_services.sh — run INSIDE the eds pod.
#
# Discovers every Kubernetes Service named "<name>-ext-nordenort-service" in the
# pod's own namespace and prints, one per line:
#
#     <name>    <clusterIP>
#
# where <name> is the Service-name prefix BEFORE "-ext-nordenort-service"
# (e.g. the "hue-ext-nordenort-service" Service -> "hue").
#
# WHY: the eds collector needs the sensor services' ClusterIPs (the PVC
# ips.txt), which differ per cluster (sandbox vs prod) and change if a Service
# is recreated. Discovering them at runtime from the API removes the hand-
# maintained, environment-specific ips.txt divergence.
#
# PRIMARY mechanism: the in-cluster Kubernetes API, authenticated with the pod's
# mounted ServiceAccount token (needs RBAC: list "services" in the namespace).
# FALLBACK: the Service ClusterIPs that Kubernetes injects as env vars
# (<NAME>_SERVICE_HOST). The fallback only sees Services that existed when THIS
# pod started, so the API path is preferred.
#
# Usage:
#   ./discover_services.sh                 # "<name>  <clusterIP>" lines
#   ./discover_services.sh --ips-only      # just the ClusterIPs (one per line)
#   ./discover_services.sh --names-only    # just the name prefixes
#
# Requires (all present in the eds image): curl, jq. Pure bash otherwise.
set -euo pipefail

SUFFIX="-ext-nordenort-service"
MODE="pairs"
case "${1:-}" in
  --ips-only)   MODE="ips" ;;
  --names-only) MODE="names" ;;
  "" )          ;;
  * ) echo "Usage: $(basename "$0") [--ips-only|--names-only]" >&2; exit 2 ;;
esac

SA_DIR="/var/run/secrets/kubernetes.io/serviceaccount"
API="https://${KUBERNETES_SERVICE_HOST:-kubernetes.default.svc}:${KUBERNETES_SERVICE_PORT:-443}"

emit() {
  # Always emit the raw pair "<name>\t<clusterIP>"; projection to the requested
  # MODE happens once, after sorting, in the dispatch block at the bottom.
  printf '%s\t%s\n' "$1" "$2"
}

discover_via_api() {
  [ -r "${SA_DIR}/token" ] || return 1
  command -v curl >/dev/null 2>&1 || return 1
  command -v jq   >/dev/null 2>&1 || return 1

  local ns token out
  ns="$(cat "${SA_DIR}/namespace" 2>/dev/null || echo default)"
  token="$(cat "${SA_DIR}/token")"

  out="$(curl -sS --fail \
            --cacert "${SA_DIR}/ca.crt" \
            -H "Authorization: Bearer ${token}" \
            "${API}/api/v1/namespaces/${ns}/services" 2>/dev/null)" || return 1

  # name must END WITH the suffix; strip the suffix to get the prefix.
  # skip headless services (clusterIP == "None") and empty clusterIPs.
  echo "$out" | jq -r --arg sfx "$SUFFIX" '
    .items[]
    | select(.metadata.name | endswith($sfx))
    | select(.spec.clusterIP != null and .spec.clusterIP != "" and .spec.clusterIP != "None")
    | "\(.metadata.name | sub($sfx + "$"; ""))\t\(.spec.clusterIP)"
  ' | while IFS=$'\t' read -r name ip; do emit "$name" "$ip"; done
}

discover_via_env() {
  # Kubernetes injects <SANITIZED_NAME>_SERVICE_HOST=<clusterIP>.
  # e.g. service "hue-ext-nordenort-service" -> HUE_EXT_NORDENORT_SERVICE_SERVICE_HOST
  # Reconstruct the prefix from the sanitized env name.
  local env_suffix sanitized_suffix
  # suffix "-ext-nordenort-service" -> env form "_EXT_NORDENORT_SERVICE"
  sanitized_suffix="$(echo "$SUFFIX" | tr 'a-z-' 'A-Z_')"   # -> _EXT_NORDENORT_SERVICE
  env_suffix="${sanitized_suffix}_SERVICE_HOST"             # k8s appends _SERVICE_HOST

  # iterate env, match <X><_EXT_NORDENORT_SERVICE>_SERVICE_HOST
  while IFS='=' read -r key val; do
    case "$key" in
      *"${env_suffix}")
        # strip the trailing env_suffix, lower-case to get the real prefix
        local prefix="${key%${env_suffix}}"
        prefix="$(echo "$prefix" | tr 'A-Z_' 'a-z-')"
        [ -n "$val" ] && emit "$prefix" "$val"
        ;;
    esac
  done < <(env)
}

# --- gather raw "<name>\t<ip>" pairs from the first source that works --------
TMP="$(mktemp /tmp/discover_services.XXXXXX)"
trap 'rm -f "$TMP"' EXIT

if discover_via_api > "$TMP" 2>/dev/null && [ -s "$TMP" ]; then
  :
else
  echo "discover_services.sh: API discovery unavailable, falling back to injected env vars" >&2
  discover_via_env > "$TMP"
fi

# --- sort by service name, then project to the requested MODE ----------------
sort -t$'\t' -k1,1 "$TMP" | while IFS=$'\t' read -r name ip; do
  [ -n "$name" ] || continue
  case "$MODE" in
    ips)   printf '%s\n' "$ip" ;;
    names) printf '%s\n' "$name" ;;
    *)     printf '%s\t%s\n' "$name" "$ip" ;;
  esac
done

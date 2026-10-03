#!/bin/bash
# Generic build + push + deploy for a single sensor pod.
#
# Usage:
#   container/build_pod.sh <app> [tag]
#
#   <app>  one of: hue humi rain wind elprice   (the <app>pod/ directory)
#   [tag]  optional image tag. Default: <app>-YYYYmmddHHMMSS (timestamp).
#
# Why this exists: hue/humi/rain/wind had NO build script, so their images were
# built ad-hoc and drifted between sandbox and prod (and the eds image series
# once filled holken2's disk because nothing pruned old builds). This one script
# gives every pod the same safe, repeatable path: Docker Hub preflight, build +
# push, apply the FULL manifest (so env/volume/probe changes land, not just the
# image), then prune old images to protect disk.
#
# Conventions assumed (hold for all current pods):
#   dir        = container/<app>pod
#   image      = magnuscj/<app>
#   deployment = <app>-deployment
#   container  = <app>            (container name inside the deployment)
#   manifest   = <app>pod/deploy.yaml  OR  <app>pod/<app>_deploy.yaml
#
# Run from anywhere; the script locates the repo and pod dir itself.
# Check your kube context first (sandbox = docker-desktop, prod = minikube):
#   kubectl config current-context
set -euo pipefail

APP="${1:-}"
if [[ -z "$APP" ]]; then
  echo "Usage: $(basename "$0") <app> [tag]   (app = hue|humi|rain|wind|elprice)" >&2
  exit 2
fi

# Resolve the repo's container/ dir relative to this script, then the pod dir.
CONTAINER_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
POD_DIR="${CONTAINER_DIR}/${APP}pod"
if [[ ! -d "$POD_DIR" ]]; then
  echo "ERROR: pod directory not found: $POD_DIR" >&2
  echo "       <app> must be one of: hue humi rain wind elprice" >&2
  exit 2
fi
cd "$POD_DIR"

IMAGE="magnuscj/${APP}"
DEPLOYMENT="${APP}-deployment"
CONTAINER="${APP}"
TAG="${2:-${APP}-$(date +%Y%m%d%H%M%S)}"

# Locate the deploy manifest (filename differs per pod).
if   [[ -f "${APP}_deploy.yaml" ]]; then MANIFEST="${APP}_deploy.yaml"
elif [[ -f "deploy.yaml"        ]]; then MANIFEST="deploy.yaml"
else
  echo "ERROR: no deploy manifest found in $POD_DIR (expected ${APP}_deploy.yaml or deploy.yaml)" >&2
  exit 2
fi
GENERATED="${MANIFEST%.yaml}.gen.yaml"

echo "App:        ${APP}"
echo "Image:      ${IMAGE}:${TAG}"
echo "Manifest:   ${MANIFEST}"
echo "Context:    $(kubectl config current-context 2>/dev/null || echo '?')"

# --- Docker Hub auth preflight -------------------------------------------
# Fail early (before a long build) if not logged in to Docker Hub.
if ! docker system info 2>/dev/null | grep -q "Username:"; then
  if ! grep -q "index.docker.io" "${HOME}/.docker/config.json" 2>/dev/null; then
    echo "ERROR: Not logged in to Docker Hub. Run: docker login -u magnuscj" >&2
    exit 1
  fi
fi

# --- Build + push --------------------------------------------------------
docker image build -t "${IMAGE}:${TAG}" .
docker push "${IMAGE}:${TAG}"

# --- Deploy --------------------------------------------------------------
# Produce a concrete manifest with the real tag. The manifest's image line may
# be either `image: magnuscj/<app>:REPLACE` (placeholder) or a hardcoded
# `:<sometag>`; rewrite whichever it is to the tag we just pushed. Applying the
# FULL manifest (not just `kubectl set image`) ensures any pod-spec change in
# the manifest actually reaches the cluster.
cp "${MANIFEST}" "${GENERATED}"
sed -i -E "s#(image: ${IMAGE}):[^[:space:]]+#\1:${TAG}#g" "${GENERATED}"
# Also honour an explicit REPLACE placeholder if present.
sed -i "s/REPLACE/${TAG}/g" "${GENERATED}"

if kubectl get deployment "${DEPLOYMENT}" >/dev/null 2>&1; then
  echo "Deployment exists -> applying manifest + rolling update to ${IMAGE}:${TAG}"
  kubectl apply -f "${GENERATED}"
  kubectl set image "deployment/${DEPLOYMENT}" "${CONTAINER}=${IMAGE}:${TAG}"
else
  echo "Deployment not found -> creating from ${GENERATED}"
  kubectl apply -f "${GENERATED}"
fi

kubectl rollout status "deployment/${DEPLOYMENT}" --timeout=240s 2>&1 | tail -2
echo "Done. ${IMAGE}:${TAG} on context $(kubectl config current-context 2>/dev/null)."

# --- Image cleanup: keep only the 5 most-recent ${IMAGE} images ----------
# Prevents unbounded image accumulation from filling the host disk. Docker
# refuses to remove an in-use image, so the running pod is always safe.
echo "Pruning old ${IMAGE} images (keeping newest 5)..."
docker images "${IMAGE}" --format '{{.Tag}}\t{{.CreatedAt}}' \
  | sort -k2 -r \
  | tail -n +6 \
  | awk '{print $1}' \
  | while read -r t; do
      echo "  removing ${IMAGE}:${t}"
      docker rmi "${IMAGE}:${t}" 2>/dev/null || echo "    (in use or already gone; skipped)"
    done
echo "Pruning dangling image layers..."
docker image prune -f 2>&1 | tail -1

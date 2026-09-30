#!/bin/bash
# Build, push and deploy the elprice pod.
#
# Usage:
#   ./build_elprice.sh [tag]
#
# If no tag is given a timestamp tag (elprice-YYYYmmddHHMMSS) is used.
# Mirrors ../../build.sh but targets the elprice image/deployment and runs
# from this pod directory. Intended to be tested on the sandbox
# (kube context "docker-desktop").
set -euo pipefail

# Always operate from the directory this script lives in.
cd "$(dirname "$0")"

IMAGE="magnuscj/elprice"
DEPLOYMENT="elprice-deployment"
CONTAINER="elprice"                 # container name in elprice_deploy.yaml
MANIFEST="elprice_deploy.yaml"
GENERATED="elprice_deploy.gen.yaml"

TAG="${1:-elprice-$(date +%Y%m%d%H%M%S)}"
echo "Image tag: ${IMAGE}:${TAG}"

# --- Docker Hub auth preflight -------------------------------------------
# Fail early with a clear message if we are not logged in to Docker Hub,
# rather than after a long build.
if ! docker system info 2>/dev/null | grep -q "Username:"; then
  # 'docker system info' does not always print Username (e.g. cred-store setups),
  # so fall back to checking the config for a Docker Hub auth entry.
  if ! grep -q "index.docker.io" "${HOME}/.docker/config.json" 2>/dev/null; then
    echo "ERROR: Not logged in to Docker Hub. Run: docker login -u magnuscj" >&2
    exit 1
  fi
fi

# --- Build + push --------------------------------------------------------
docker image build -t "${IMAGE}:${TAG}" .
docker push "${IMAGE}:${TAG}"

# --- Deploy --------------------------------------------------------------
# Substitute the REPLACE placeholder in the manifest with the real tag.
cp "${MANIFEST}" "${GENERATED}"
sed -i "s/REPLACE/${TAG}/g" "${GENERATED}"

if kubectl get deployments 2>/dev/null | grep -q "${DEPLOYMENT}"; then
  echo "Deployment exists -> rolling update to ${IMAGE}:${TAG}"
  kubectl set image "deployments/${DEPLOYMENT}" "${CONTAINER}=${IMAGE}:${TAG}"
  kubectl apply -f "${GENERATED}"
else
  echo "Deployment not found -> creating from ${GENERATED}"
  kubectl apply -f "${GENERATED}"
fi

echo "Done. Context: $(kubectl config current-context 2>/dev/null), tag: ${TAG}"

# --- Image cleanup: keep only the 5 most-recent ${IMAGE} images ----------
# Prevents unbounded image accumulation from filling the host disk (the eds
# series once filled holken2 to 100% and crashed mysqld, 2026-09-30). Docker
# refuses to remove an in-use image, so the running pod is safe even if its
# tag falls outside the newest 5.
echo "Pruning old ${IMAGE} images (keeping newest 5)..."
docker images "${IMAGE}" --format '{{.Tag}}\t{{.CreatedAt}}' \
  | sort -k2 -r \
  | tail -n +6 \
  | awk '{print $1}' \
  | while read -r tag; do
      echo "  removing ${IMAGE}:${tag}"
      docker rmi "${IMAGE}:${tag}" 2>/dev/null || echo "    (in use or already gone; skipped)"
    done
echo "Pruning dangling image layers..."
docker image prune -f 2>&1 | tail -1

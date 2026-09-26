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

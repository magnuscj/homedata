echo $1
docker image build --build-arg CACHE_DATE=$(date +%Y-%m-%d:%H:%M:%S) -t magnuscj/eds:$1 .;docker push magnuscj/eds:$1

kubectl get deployments | grep -q eds-deployment  && DEP="true" || DEP="false"

if [[ "$DEP" == "true" ]]
then
  # Regenerate the manifest with the new tag and APPLY it. Applying (not just
  # `kubectl set image`) is required so that ANY change in eds.yaml beyond the
  # image — env/secretKeyRef, volumes, probes — actually reaches the cluster.
  # (2026-10-01: a set-image-only deploy silently dropped the new DB_PASSWORD
  # secretKeyRef, crash-looping the pod on missing env.) apply also updates the
  # image, so the explicit set image is redundant but kept as a harmless no-op.
  cp eds.yaml eds_deploy.yaml
  sed -i "s/REPLACE/$1/g" eds_deploy.yaml
  kubectl apply -f eds_deploy.yaml
  kubectl set image deployments/eds-deployment eds=magnuscj/eds:$1

else
  cp eds.yaml eds_deploy.yaml
  sed -i "s/REPLACE/$1/g" eds_deploy.yaml
  kubectl apply -f eds_deploy.yaml
fi

# --- Image cleanup: keep only the 5 most-recent magnuscj/eds:eds-t* images ---
# Old builds accumulate at ~1.5GB each and once filled the holken2 root disk to
# 100%, crashing mysqld mid-deploy (2026-09-30). Prune here, newest-5 retained.
# Only touches the eds-t* tag series; never removes the tag just deployed ($1)
# because it is by definition among the newest. Untagged/dangling layers freed
# by removals are reclaimed too. Docker refuses to remove an in-use image, so
# the running pod's image is safe even if it falls outside the newest 5.
echo "Pruning old eds-t* images (keeping newest 5)..."
docker images 'magnuscj/eds' --format '{{.Tag}}\t{{.CreatedAt}}' \
  | grep -E '^eds-t[0-9]+' \
  | sort -k2 -r \
  | tail -n +6 \
  | awk '{print $1}' \
  | while read -r tag; do
      echo "  removing magnuscj/eds:$tag"
      docker rmi "magnuscj/eds:$tag" 2>/dev/null || echo "    (in use or already gone; skipped)"
    done
echo "Remaining eds-t* images:"
docker images 'magnuscj/eds' --format '{{.Tag}}' | grep -E '^eds-t[0-9]+' | sort -r | head
# Reclaim dangling (untagged) layers orphaned by rebuilds. Safe: never removes
# a tagged or in-use image. (2026-09-30: 12 dangling images held ~15GB.)
echo "Pruning dangling image layers..."
docker image prune -f 2>&1 | tail -1
#kubectl port-forward --address 192.168.1.171 svc/eds-ext-nordenort-service  8181:80 &

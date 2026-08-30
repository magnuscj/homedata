#!/bin/bash
# Build and deploy using a locally built image
# Uses a timestamp tag to force Kubernetes to use the new image
# Works with both Docker Desktop and minikube

TAG="local-$(date +%Y%m%d%H%M%S)"
echo "Building image: magnuscj/eds:$TAG"

docker image build --build-arg CACHE_DATE=$(date +%Y-%m-%d:%H:%M:%S) -t magnuscj/eds:$TAG .

# For minikube: load image into minikube's image store
if kubectl config current-context 2>/dev/null | grep -q minikube; then
  echo "Minikube detected, loading image..."
  minikube image load magnuscj/eds:$TAG
fi

cp eds.yaml eds_deploy.yaml
sed -i "s/REPLACE/$TAG/g" eds_deploy.yaml

kubectl get deployments | grep -q eds-deployment && DEP="true" || DEP="false"

if [[ "$DEP" == "true" ]]
then
  kubectl apply -f eds_deploy.yaml
  kubectl rollout restart deployment/eds-deployment
else
  kubectl apply -f eds_deploy.yaml
fi

echo "Deployed with tag: $TAG"

echo $1
docker image build --build-arg CACHE_DATE=$(date +%Y-%m-%d:%H:%M:%S) -t magnuscj/eds:$1 .;docker push magnuscj/eds:$1

kubectl get deployments | grep -q eds-deployment  && DEP="true" || DEP="false"

if [[ "$DEP" -eq "true" ]]
then 
  kubectl set image deployments/eds-deployment  eds=magnuscj/eds:$1
else
  cp eds.yaml eds_deploy.yaml
  sed -i "s/REPLACE/$1/g" eds_deploy.yaml
  kubectl apply -f eds_deploy.yaml
fi

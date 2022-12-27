echo $1
docker image build --build-arg CACHE_DATE=$(date +%Y-%m-%d:%H:%M:%S) -t magnuscj/eds:$1 .;docker push magnuscj/eds:$1
cp eds.yaml eds_deploy.yaml
sed -i "s/REPLACE/$1/g" eds_deploy.yaml
kubectl apply -f eds_deploy.yaml

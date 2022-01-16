echo $1
docker image build -t magnuscj/eds:$1 .;docker push magnuscj/eds:$1
cp eds.yaml eds_deploy.yaml
sed -i "s/REPLACE/$1/g" eds_deploy.yaml
kubectl apply -f eds_deploy.yaml

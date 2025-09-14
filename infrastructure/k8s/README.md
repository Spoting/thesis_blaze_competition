# Microk8s Installation

1. Be sure to `vagrant up` the Vagrant VM in `infrastructure/vagrant/vm-kube`

2. Install 
```
sudo snap install microk8s --classic
```

3. Firewall Access
```
sudo ufw allow in on ethh0 && sudo ufw allow out on eth0
sudo ufw default allow routed
```

4. Non Root Access
```
sudo usermod -a -G microk8s $USER
mkdir ~/.kube
sudo chown -f -R $USER ~/.kube
echo "alias k='microk8s.kubectl'" >> .profile
echo "alias c=clear" >> .profile
sudo su - $USER
```

5. Get Config of Cluster
```
microk8s.kubectl config view
```

Merge it in host's `.kube/config`
If trying remote access, should use Cluster IP as Server Name in Config

6. Activate Docker Registry of microk8s
```
microk8s enable registry
```

## Dashboard

1. Install
```
microk8s enable dashboard
```

2. Verify
```
kubectl get pods -n kube-system -l k8s-app=kubernetes-dashboard
```

3. Create Access Token
```
microk8s kubectl create token default
```

4. Port forward from your host
```
kubectl port-forward -n kube-system service/kubernetes-dashboard 10443:443
```

5. Access from Localhost
```
https://localhost:10443/#/login
```

## Creating Certs
```
kubectl create secret tls blaze-competition-tls --cert=tls.pem --key=tls.key --namespace=blaze-competition
```


# KEDA

## Enable
`microk8s.enable keda`

## Check Objects
```
microk8s.kubectl get pods -n keda

kubectl get scaledobject -n blaze-competition

kubectl get hpa -n blaze-competition
```

# Useful Commands

## Delete Namespace
```
kubectl delete namespace blaze-competition
```

## Apply Manifests
```
kubectl apply -f infrastructure/k8s/manifests
```

## Apply Kustomization

```
kubectl apply -k infrastructure/k8s/
```
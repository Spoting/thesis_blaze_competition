# TODO

- KEDA

- Vagrant apply nameservers

- Secrets

- Kustomization Using Global Variables 

- Import SQL
- Backup SQL

- Grafana 
- Prometheus

-- Optional

- Helm
- ArgoCD


# KEDA
microk8s.enable keda


microk8s.kubectl get pods -n keda

kubectl get scaledobject -n blaze-competition

kubectl get hpa -n blaze-competition



# Delete Namespace
```
kubectl delete namespace blaze-competition
```

# Reapply Namespace
```
kubectl apply -f <your-k8s-folder-path>/ --namespace=blaze-competition
```
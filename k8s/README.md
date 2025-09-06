# KEDA
microk8s.enable keda

microk8s.kubectl get pods -n keda

kubectl get scaledobject -n blaze-competition

kubectl get hpa -n blaze-competition


# Delete Namespace
```
kubectl delete namespace blaze-competition
```

# Apply Manifests
```
kubectl apply -f k8s/manifests
```

# Apply Kustomization

```
kubectl apply -k k8s/
```
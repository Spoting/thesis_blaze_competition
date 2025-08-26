# Give access of kubectl to Jenkins
1. Generate kubeconfig `microk8s config > microk8s-config`
2. Sync to your Host. to `${HOME}/.kube/microk8s-config`
3. Remember to edit the config and replace server with Vagrant's IP.

# Build Jenkins Image
- `docker compose up -d --build`

- `docker compose up -d`

- `docker compose down`


# Setup
1. Fetch Password `docker compose logs jenkins` ( will be printed to logs )
2. GOTO: `localhost:8099` and insert Password
3. Install Recommended Packages
4. Create your Admin User



# Setup Access for Repository ( SSH Keys ).

1. Create a new SSH key for Jenkins use explicity.
```
docker compose exec -u jenkins jenkins /bin/bash

cd /var/jenkins_home/

ssh-keygen -t rsa -b 2048 // Enter no passphrase
```

2. Copy public key to Github

1. GOTO: `http://localhost:8099/manage/credentials/store/system/domain/_/newCredentials` and pass your private key of Github.
2. Modify Setting: Host Key Verification Strategy -> 'Accept First Connection'
3. Create new Item (Pipeline) with Pooling instead of Webhook ( since we are localhost ).
4. Remember to configure 'Polling ignores commits in certain paths' Exclude k8s/.* , to avoid infinite build loops

# Create your Jenkinsfile to / of application.
1. Trigger a commit.
2. Check pipeline success
3. Check microk8s registry `curl 192.168.56.11:32000/v2/app-php-prod/tags/list`

GG Young Padawan
# Local Installation
1. Build Containers : ```make build-clean```

2. Initialize Permissions : ```make fix-perms```

3. Setup Custom SSL : ```mkcert -cert-file frankenphp/certs/tls.pem -key-file frankenphp/certs/tls.key "symfony.localhost"```

4. Trust Custom SSL : ```mkcert -install```

5. GOTO: https://symfony.localhost or your SERVER_NAME enviromental

# README Catalog
* **[`Project Structure`](/docs/project_structure.md)**: Documentation for the API or library provided by Component C.
* **[`Jenkins`](/infrastructure/jenkins/README.md)**: A brief description of what Component A does.
* **[`Kubernetes`](/infrastructure/k8s/README.md)**: A brief description of what Component A does.
* **[`Ansible`](/infrastructure/ansible/README.md)**: A brief description of what Component A does.
* **[`Database/Doctrine Commands`](/docs/database.md)**: Details on how to set up and use Component B.


# Usage / General Info

### Start / Stop Project
```
make up; make down;
```

### Exec in PHP container:
```
make php-bash
```

### Exec Pod :
```
make shell <pod>
```

### Logs of specific Container ( or no parameter for all containers):
```
make logs php
```

### Worker(s) Logs :
```
make worker-logs
```

### Services local url:
```
Mailer:
http://localhost:8026/

RedisInsight:
http://localhost:5540/

RabbitMQ Management:
http://localhost:15672/#/queues

u: guest
p: guest
```


### Load Database with test data (dev mode only). 
```
php bin/console doctrine:fixtures:load
```

### Dev Admin Login
```
https://symfony.localhost/login

u: admin@symfony.com
p: 1234
```

### Assets Generation (Tailwind/JS) 
- Build CSS (dev): 
```
php bin/console tailwind:build --watch 
```
- If there is Permission Error do below 
```
docker compose exec php /bin/bash
php bin/console tailwind:build
exit
make fix-perms
```
- Generate Assets ( css / js ) :
```
php bin/console asset-map:compile
```

### Require NPM modules :
```
php bin/console importmap:require chartjs-plugin-zoom
```

## Build Production Image
```
docker compose -f compose.yaml -f compose.prod.yaml build --pull --no-cache
```


**Enjoy!**